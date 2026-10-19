<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Messagerie interne : accès, création de conversations, envoi, lecture,
 * non-lus, pièces jointes et droits.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function direction(): User
    {
        return $this->user('direction@epsv.cg');
    }

    private function teacher(): User
    {
        return $this->user('prof.mabiala@epsv.cg');
    }

    private function directConversation(User $first, User $second): Conversation
    {
        $this->actingAs($first)
            ->post(route('chat.conversations.store'), [
                'type' => Conversation::TYPE_DIRECT,
                'user_id' => $second->id,
            ])
            ->assertRedirect();

        return Conversation::query()->where('type', Conversation::TYPE_DIRECT)->latest('id')->firstOrFail();
    }

    public function test_invite_redirige_vers_la_connexion(): void
    {
        $this->get(route('chat.index'))->assertRedirect('/login');
    }

    public function test_ouverture_de_la_messagerie_rattache_au_canal_general(): void
    {
        $user = $this->teacher();
        $general = Conversation::general();

        $this->assertNotNull($general, 'Le canal général doit être créé par migration.');
        $this->assertFalse($general->hasParticipant($user));

        $this->actingAs($user)
            ->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Messagerie')
            ->assertSee("Toute l'école");

        $this->assertTrue($general->fresh()->hasParticipant($user));
    }

    public function test_creation_dune_conversation_directe_est_idempotente(): void
    {
        $direction = $this->direction();
        $teacher = $this->teacher();

        $this->actingAs($direction)
            ->post(route('chat.conversations.store'), [
                'type' => Conversation::TYPE_DIRECT,
                'user_id' => $teacher->id,
            ])
            ->assertRedirect();

        $conversation = Conversation::query()->where('type', Conversation::TYPE_DIRECT)->firstOrFail();

        $this->actingAs($teacher)
            ->post(route('chat.conversations.store'), [
                'type' => Conversation::TYPE_DIRECT,
                'user_id' => $direction->id,
            ])
            ->assertRedirect(route('chat.index', ['c' => $conversation->id]));

        $this->assertSame(1, Conversation::query()->where('type', Conversation::TYPE_DIRECT)->count());
        $this->assertTrue($conversation->hasParticipant($direction));
        $this->assertTrue($conversation->hasParticipant($teacher));
    }

    public function test_un_groupe_est_cree_avec_ses_participants(): void
    {
        $direction = $this->direction();
        $teacher = $this->teacher();
        $secretariat = $this->user('secretariat@epsv.cg');

        $this->actingAs($direction)
            ->post(route('chat.conversations.store'), [
                'type' => Conversation::TYPE_GROUP,
                'name' => 'Conseil de classe',
                'user_ids' => [$teacher->id, $secretariat->id],
            ])
            ->assertRedirect();

        $group = Conversation::query()->where('type', Conversation::TYPE_GROUP)->firstOrFail();

        $this->assertSame('Conseil de classe', $group->name);
        $this->assertSame(3, $group->participants()->count());
    }

    public function test_un_non_participant_ne_peut_pas_lire_ni_ecrire(): void
    {
        $direction = $this->direction();
        $teacher = $this->teacher();
        $intruder = $this->user('surveillant@epsv.cg');

        $conversation = $this->directConversation($direction, $teacher);

        $this->actingAs($intruder)->get(route('chat.index', ['c' => $conversation->id]))->assertNotFound();
        $this->actingAs($intruder)->getJson(route('chat.messages.poll', $conversation))->assertForbidden();
        $this->actingAs($intruder)
            ->postJson(route('chat.messages.store', $conversation), ['body' => 'Intrusion'])
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_envoi_et_reception_dun_message(): void
    {
        $sender = $this->teacher();
        $recipient = $this->direction();
        $conversation = $this->directConversation($sender, $recipient);

        $response = $this->actingAs($sender)
            ->postJson(route('chat.messages.store', $conversation), ['body' => 'Bonjour la direction']);

        $response->assertOk()
            ->assertJsonStructure(['html', 'id', 'last_id']);

        $message = Message::firstOrFail();
        $this->assertSame('Bonjour la direction', $message->body);
        $this->assertSame($sender->id, $message->user_id);

        $poll = $this->actingAs($recipient)
            ->getJson(route('chat.messages.poll', $conversation).'?after_id=0');

        $poll->assertOk();
        $this->assertStringContainsString('Bonjour la direction', $poll->json('html'));
        $this->assertSame($message->id, $poll->json('last_id'));
    }

    public function test_un_message_vide_sans_piece_jointe_est_refuse(): void
    {
        $sender = $this->teacher();
        $recipient = $this->direction();
        $conversation = $this->directConversation($sender, $recipient);

        $this->actingAs($sender)
            ->postJson(route('chat.messages.store', $conversation), ['body' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_compteur_de_non_lus_et_marquage_comme_lu(): void
    {
        $sender = $this->teacher();
        $recipient = $this->direction();
        $conversation = $this->directConversation($sender, $recipient);

        $this->actingAs($sender)->postJson(route('chat.messages.store', $conversation), ['body' => 'Premier']);
        $this->actingAs($sender)->postJson(route('chat.messages.store', $conversation), ['body' => 'Deuxième']);

        $this->assertSame(2, Conversation::unreadTotalFor($recipient));
        $this->assertSame(2, $conversation->unreadCountFor($recipient));
        $this->assertSame(0, Conversation::unreadTotalFor($sender));

        $this->actingAs($recipient)->get(route('chat.index', ['c' => $conversation->id]))->assertOk();

        $this->assertSame(0, Conversation::unreadTotalFor($recipient));
    }

    public function test_seul_lauteur_peut_supprimer_son_message(): void
    {
        $sender = $this->teacher();
        $recipient = $this->direction();
        $conversation = $this->directConversation($sender, $recipient);

        $this->actingAs($sender)->postJson(route('chat.messages.store', $conversation), ['body' => 'À supprimer']);
        $message = Message::firstOrFail();

        $this->actingAs($recipient)
            ->deleteJson(route('chat.messages.destroy', $message))
            ->assertForbidden();

        $this->actingAs($sender)
            ->deleteJson(route('chat.messages.destroy', $message))
            ->assertOk();

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_piece_jointe_reservee_aux_participants(): void
    {
        Storage::fake('local');

        $sender = $this->teacher();
        $recipient = $this->direction();
        $intruder = $this->user('surveillant@epsv.cg');
        $conversation = $this->directConversation($sender, $recipient);

        $this->actingAs($sender)->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Voici le document',
            'attachment' => UploadedFile::fake()->create('bulletin.pdf', 120, 'application/pdf'),
        ])->assertOk();

        $message = Message::firstOrFail();
        $this->assertTrue($message->hasAttachment());
        Storage::disk('local')->assertExists($message->attachment_path);

        $this->actingAs($recipient)
            ->get(route('chat.attachment', $message))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($intruder)
            ->get(route('chat.attachment', $message))
            ->assertForbidden();
    }
}
