<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatPushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Messagerie interne du personnel : canal général, groupes et conversations
 * directes. Le rafraîchissement se fait par interrogation périodique
 * (quelques secondes), sans serveur WebSocket à maintenir.
 */
class ChatController extends Controller
{
    private const MAX_MESSAGE_LENGTH = 4000;

    private const MAX_ATTACHMENT_KB = 10240;

    public function index(Request $request)
    {
        $user = $request->user();

        $this->joinGeneralChannel($user);

        $conversations = Conversation::query()
            ->forUser($user->getKey())
            ->with(['participants:id,name,job_title', 'lastMessage'])
            ->get()
            ->sortByDesc(fn (Conversation $conversation) => $conversation->last_message_at ?? $conversation->created_at)
            ->values();

        $selected = null;

        if (! $request->boolean('list') && $request->filled('c')) {
            $selected = $conversations->firstWhere('id', (int) $request->input('c'));
            abort_unless($selected, 404);
        } elseif (! $request->boolean('list')) {
            $selected = $conversations->first();
        }

        $messages = collect();

        if ($selected) {
            $selected->markReadBy($user);

            $messages = $selected->messages()
                ->with('user:id,name,job_title')
                ->latest('id')
                ->limit(60)
                ->get()
                ->reverse()
                ->values();
        }

        $unread = $conversations->mapWithKeys(
            fn (Conversation $conversation) => [$conversation->id => $conversation->unreadCountFor($user)]
        );

        $canManageGroup = $selected
            && $selected->type === Conversation::TYPE_GROUP
            && (int) $selected->created_by === (int) $user->getKey();

        // Membres potentiels : tous les comptes actifs + les membres actuels
        // même désactivés, pour qu'ils restent visibles et gérables.
        $groupCandidates = collect();

        if ($canManageGroup) {
            $memberIds = $selected->participants->pluck('id');

            $groupCandidates = User::query()
                ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $memberIds))
                ->orderBy('name')
                ->get(['id', 'name', 'job_title', 'is_active']);
        }

        return view('chat.index', [
            'conversations' => $conversations,
            'selected' => $selected,
            'messages' => $messages,
            'unread' => $unread,
            'colleagues' => $this->colleagues($user),
            'canManageGroup' => $canManageGroup,
            'groupCandidates' => $groupCandidates,
        ]);
    }

    /**
     * Total de non-lus pour le badge global (barre latérale, titre, favicon).
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->joinGeneralChannel($user);

        return response()->json([
            'total' => Conversation::unreadTotalFor($user),
        ]);
    }

    /** Crée (ou retrouve) une conversation directe, ou un groupe. */
    public function storeConversation(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', Rule::in([Conversation::TYPE_DIRECT, Conversation::TYPE_GROUP])],
            'name' => ['nullable', 'required_if:type,group', 'string', 'max:80'],
            'user_id' => [
                'nullable',
                'required_if:type,direct',
                'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
            'user_ids' => ['nullable', 'required_if:type,group', 'array', 'min:1'],
            'user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
        ]);

        if ($data['type'] === Conversation::TYPE_DIRECT) {
            $otherId = (int) $data['user_id'];

            if ($otherId === (int) $user->getKey()) {
                throw ValidationException::withMessages([
                    'user_id' => 'Choisissez un autre destinataire.',
                ]);
            }

            $conversation = Conversation::firstOrCreate(
                ['direct_key' => Conversation::directKeyFor($user->getKey(), $otherId)],
                ['type' => Conversation::TYPE_DIRECT, 'created_by' => $user->getKey()],
            );

            foreach ([$user->getKey(), $otherId] as $participantId) {
                $this->attachIfMissing($conversation, (int) $participantId);
            }
        } else {
            $participantIds = collect($data['user_ids'])
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === (int) $user->getKey())
                ->unique()
                ->values();

            if ($participantIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Choisissez au moins un participant.',
                ]);
            }

            $conversation = Conversation::create([
                'type' => Conversation::TYPE_GROUP,
                'name' => $data['name'],
                'created_by' => $user->getKey(),
            ]);

            $this->attachIfMissing($conversation, (int) $user->getKey());

            foreach ($participantIds as $participantId) {
                $this->attachIfMissing($conversation, $participantId);
            }
        }

        return redirect()->route('chat.index', ['c' => $conversation->id]);
    }

    /**
     * Renomme un groupe et synchronise ses membres.
     * Réservé au créateur : il ne peut pas se retirer lui-même.
     */
    public function updateConversation(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();

        $this->authorizeParticipant($conversation, $user);

        abort_unless($conversation->type === Conversation::TYPE_GROUP, 403);
        abort_unless((int) $conversation->created_by === (int) $user->getKey(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        // Le créateur reste toujours membre, même s'il n'est pas coché.
        $selectedIds = collect($data['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->push((int) $user->getKey())
            ->unique()
            ->values();

        $currentIds = $conversation->participants()->pluck('users.id');
        $toAdd = $selectedIds->diff($currentIds);
        $toRemove = $currentIds
            ->diff($selectedIds)
            ->reject(fn ($id) => (int) $id === (int) $conversation->created_by);

        $latestMessageId = (int) $conversation->messages()->max('id');

        foreach ($toAdd as $participantId) {
            $conversation->participants()->attach($participantId, [
                'last_read_message_id' => $latestMessageId,
            ]);
        }

        if ($toRemove->isNotEmpty()) {
            $conversation->participants()->detach($toRemove->all());
        }

        $conversation->update(['name' => $data['name']]);

        return redirect()
            ->route('chat.index', ['c' => $conversation->id])
            ->with('success', 'Groupe mis à jour.');
    }

    /** Envoie un message (texte et/ou pièce jointe). */
    public function storeMessage(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        $this->authorizeParticipant($conversation, $user);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:'.self::MAX_MESSAGE_LENGTH],
            'attachment' => [
                'nullable',
                'file',
                'max:'.self::MAX_ATTACHMENT_KB,
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            ],
        ]);

        $file = $request->file('attachment');

        if (blank($data['body'] ?? null) && $file === null) {
            throw ValidationException::withMessages([
                'body' => 'Écrivez un message ou joignez un fichier.',
            ]);
        }

        $message = $conversation->messages()->create([
            'user_id' => $user->getKey(),
            'body' => $data['body'] ?? null,
            'attachment_path' => $file?->store('chat/'.$conversation->getKey(), 'local'),
            'attachment_name' => $file?->getClientOriginalName(),
            'attachment_mime' => $file?->getMimeType(),
            'attachment_size' => $file?->getSize(),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();
        $conversation->markReadBy($user);

        $message->load('user:id,name,job_title');

        // Envoi des notifications push après la réponse HTTP : l'expéditeur
        // n'attend pas les appels aux services de push des navigateurs.
        app()->terminating(function () use ($message) {
            app(ChatPushNotifier::class)->notify($message);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('chat.partials.message', ['message' => $message])->render(),
                'id' => $message->id,
                'last_id' => $message->id,
            ]);
        }

        return redirect()->route('chat.index', ['c' => $conversation->id]);
    }

    /** Nouveaux messages depuis `after_id` (interrogation périodique). */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        $this->authorizeParticipant($conversation, $user);

        $afterId = max(0, (int) $request->query('after_id', 0));

        $messages = $conversation->messages()
            ->with('user:id,name,job_title')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(100)
            ->get();

        // Ne marque comme lus que les messages effectivement renvoyés : au-delà
        // de 100 nouveaux messages, le reste sera lu au cycle suivant.
        if ($messages->isNotEmpty()) {
            $conversation->markReadBy($user, $messages->last()->id);
        }

        return response()->json([
            'html' => $messages
                ->map(fn (Message $message) => view('chat.partials.message', ['message' => $message])->render())
                ->implode(''),
            'last_id' => $messages->last()?->id ?? $afterId,
            'unread_total' => Conversation::unreadTotalFor($user),
        ]);
    }

    /** Télécharge une pièce jointe (réservé aux participants). */
    public function downloadAttachment(Request $request, Message $message)
    {
        $this->authorizeParticipant($message->conversation, $request->user());

        abort_unless(
            $message->hasAttachment() && Storage::disk('local')->exists($message->attachment_path),
            404
        );

        return Storage::disk('local')->download(
            $message->attachment_path,
            $message->attachment_name ?: 'piece-jointe'
        );
    }

    /** Supprime son propre message (suppression logique). */
    public function destroyMessage(Request $request, Message $message)
    {
        $user = $request->user();

        $this->authorizeParticipant($message->conversation, $user);

        abort_unless((int) $message->user_id === (int) $user->getKey(), 403);

        $conversation = $message->conversation;
        $message->delete();

        $conversation->forceFill([
            'last_message_at' => $conversation->messages()->latest('id')->value('created_at'),
        ])->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $message->id]);
        }

        return back()->with('success', 'Message supprimé.');
    }

    /** Rattache l'utilisateur au canal général à sa première ouverture. */
    private function joinGeneralChannel(User $user): void
    {
        $general = Conversation::general();

        if ($general && ! $general->hasParticipant($user)) {
            $general->participants()->attach($user->getKey(), [
                'last_read_message_id' => (int) $general->messages()->max('id'),
            ]);
        }
    }

    private function attachIfMissing(Conversation $conversation, int $userId): void
    {
        if (! $conversation->participants()->whereKey($userId)->exists()) {
            $conversation->participants()->attach($userId, [
                'last_read_message_id' => (int) $conversation->messages()->max('id'),
            ]);
        }
    }

    private function authorizeParticipant(Conversation $conversation, User $user): void
    {
        abort_unless($conversation->hasParticipant($user), 403);
    }

    /** @return Collection<int, User> */
    private function colleagues(User $user): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->orderBy('name')
            ->get(['id', 'name', 'job_title']);
    }
}
