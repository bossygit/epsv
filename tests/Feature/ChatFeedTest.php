<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageComment;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fil d'actualité du canal « Toute l'école » : publications, mentions
 * « J'aime » et commentaires. Les conversations privées et les groupes
 * conservent le mode messagerie classique.
 */
class ChatFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function direction(): User
    {
        return User::where('email', 'direction@epsv.cg')->firstOrFail();
    }

    private function publier(User $auteur, string $corps): Message
    {
        $canal = Conversation::general();

        return $canal->messages()->create([
            'user_id' => $auteur->getKey(),
            'body' => $corps,
        ]);
    }

    public function test_le_canal_general_s_affiche_comme_un_fil_d_actualite(): void
    {
        $direction = $this->direction();
        $this->publier($direction, 'Réunion pédagogique vendredi à 15h.');

        $this->actingAs($direction)
            ->get(route('chat.index'))
            ->assertOk()
            ->assertSee('visible par tout le personnel')
            ->assertSee('Réunion pédagogique vendredi à 15h.')
            ->assertSee('Commenter');
    }

    public function test_une_conversation_directe_reste_en_mode_messagerie(): void
    {
        $direction = $this->direction();
        $collegue = User::where('email', 'secretariat@epsv.cg')->firstOrFail();

        $directe = Conversation::create([
            'type' => Conversation::TYPE_DIRECT,
            'direct_key' => collect([$direction->id, $collegue->id])->sort()->implode('-'),
            'created_by' => $direction->getKey(),
            'last_message_at' => now(),
        ]);

        $directe->participants()->attach([$direction->getKey(), $collegue->getKey()]);

        $directe->messages()->create([
            'user_id' => $collegue->getKey(),
            'body' => 'Bonjour, un message privé.',
        ]);

        $this->actingAs($direction)
            ->get(route('chat.index', ['c' => $directe->id]))
            ->assertOk()
            ->assertDontSee('visible par tout le personnel')
            ->assertSee('Bonjour, un message privé.');
    }

    public function test_aimer_une_publication_puis_retirer_son_avis(): void
    {
        $direction = $this->direction();
        $publication = $this->publier($direction, 'Information importante.');

        $this->actingAs($direction)
            ->postJson(route('chat.messages.like', $publication))
            ->assertOk()
            ->assertJson(['liked' => true, 'count' => 1]);

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $publication->id,
            'user_id' => $direction->id,
        ]);

        $this->actingAs($direction)
            ->postJson(route('chat.messages.like', $publication))
            ->assertOk()
            ->assertJson(['liked' => false, 'count' => 0]);

        $this->assertDatabaseCount('message_reactions', 0);
    }

    public function test_commenter_une_publication(): void
    {
        $direction = $this->direction();
        $publication = $this->publier($direction, 'Sortie scolaire jeudi.');

        $this->actingAs($direction)
            ->post(route('chat.messages.comments.store', $publication), [
                'body' => 'Merci pour l’information.',
            ])
            ->assertRedirect(route('chat.index', ['c' => $publication->conversation_id]).'#post-'.$publication->id);

        $this->assertDatabaseHas('message_comments', [
            'message_id' => $publication->id,
            'user_id' => $direction->id,
            'body' => 'Merci pour l’information.',
        ]);

        $this->actingAs($direction)
            ->get(route('chat.index'))
            ->assertSee('Merci pour l’information.');
    }

    public function test_un_commentaire_vide_est_refuse(): void
    {
        $direction = $this->direction();
        $publication = $this->publier($direction, 'Annonce.');

        $this->actingAs($direction)
            ->post(route('chat.messages.comments.store', $publication), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('message_comments', 0);
    }
}
