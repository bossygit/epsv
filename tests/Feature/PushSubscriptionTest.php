<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Abonnements Web Push : enregistrement, réattribution au navigateur partagé,
 * suppression réservée à son propriétaire et validation.
 */
class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        config([
            'webpush.enabled' => true,
            'webpush.public_key' => 'test-public-key',
            'webpush.private_key' => 'test-private-key',
        ]);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(string $endpoint = 'https://push.example.com/subscription/abc'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'cle-publique-du-navigateur',
                'auth' => 'secret-auth',
            ],
            'contentEncoding' => 'aes128gcm',
        ];
    }

    public function test_un_invite_ne_peut_pas_s_abonner(): void
    {
        $this->postJson(route('push.subscriptions.store'), $this->payload())
            ->assertUnauthorized();
    }

    public function test_un_utilisateur_enregistre_son_abonnement(): void
    {
        $direction = $this->user('direction@epsv.cg');

        $this->actingAs($direction)
            ->postJson(route('push.subscriptions.store'), $this->payload())
            ->assertCreated()
            ->assertJson(['ok' => true]);

        $subscription = PushSubscription::firstOrFail();

        $this->assertSame($direction->id, $subscription->user_id);
        $this->assertSame(PushSubscription::hashEndpoint($this->payload()['endpoint']), $subscription->endpoint_hash);
        $this->assertSame('aes128gcm', $subscription->content_encoding);
    }

    public function test_le_meme_navigateur_est_reattribue_au_dernier_compte_connecte(): void
    {
        $direction = $this->user('direction@epsv.cg');
        $teacher = $this->user('prof.mabiala@epsv.cg');

        $this->actingAs($direction)
            ->postJson(route('push.subscriptions.store'), $this->payload())
            ->assertCreated();

        $this->actingAs($teacher)
            ->postJson(route('push.subscriptions.store'), $this->payload())
            ->assertCreated();

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertSame($teacher->id, PushSubscription::firstOrFail()->user_id);
    }

    public function test_un_utilisateur_ne_supprime_que_ses_propres_abonnements(): void
    {
        $direction = $this->user('direction@epsv.cg');
        $teacher = $this->user('prof.mabiala@epsv.cg');

        $own = PushSubscription::create([
            'user_id' => $direction->id,
            'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.com/direction'),
            'endpoint' => 'https://push.example.com/direction',
            'public_key' => 'p256dh',
            'auth_token' => 'auth',
        ]);

        $other = PushSubscription::create([
            'user_id' => $teacher->id,
            'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.com/prof'),
            'endpoint' => 'https://push.example.com/prof',
            'public_key' => 'p256dh',
            'auth_token' => 'auth',
        ]);

        $this->actingAs($direction)
            ->deleteJson(route('push.subscriptions.destroy'), ['endpoint' => $other->endpoint])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $other->id]);

        $this->actingAs($direction)
            ->deleteJson(route('push.subscriptions.destroy'), ['endpoint' => $own->endpoint])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $own->id]);
    }

    public function test_les_notifications_desactivees_refusent_l_abonnement(): void
    {
        config(['webpush.enabled' => false]);

        $this->actingAs($this->user('direction@epsv.cg'))
            ->postJson(route('push.subscriptions.store'), $this->payload())
            ->assertStatus(503);
    }

    public function test_la_deconnexion_desactive_l_abonnement_sans_le_supprimer(): void
    {
        $direction = $this->user('direction@epsv.cg');

        $subscription = PushSubscription::create([
            'user_id' => $direction->id,
            'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.com/direction'),
            'endpoint' => 'https://push.example.com/direction',
            'public_key' => 'p256dh',
            'auth_token' => 'auth',
        ]);

        $this->actingAs($direction)
            ->patchJson(route('push.subscriptions.disable'), ['endpoint' => $subscription->endpoint])
            ->assertOk();

        $subscription->refresh();

        $this->assertNotNull($subscription->disabled_at);
        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_une_reconnexion_reactive_l_abonnement_desactive(): void
    {
        $direction = $this->user('direction@epsv.cg');

        PushSubscription::create([
            'user_id' => $direction->id,
            'endpoint_hash' => PushSubscription::hashEndpoint($this->payload()['endpoint']),
            'endpoint' => $this->payload()['endpoint'],
            'public_key' => 'p256dh',
            'auth_token' => 'auth',
            'disabled_at' => now(),
        ]);

        $this->actingAs($direction)
            ->postJson(route('push.subscriptions.store'), $this->payload())
            ->assertCreated();

        $this->assertNull(PushSubscription::firstOrFail()->disabled_at);
    }

    public function test_l_abonnement_exige_un_endpoint_et_des_cles(): void
    {
        $this->actingAs($this->user('direction@epsv.cg'))
            ->postJson(route('push.subscriptions.store'), [
                'endpoint' => 'pas-une-url',
                'keys' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }
}
