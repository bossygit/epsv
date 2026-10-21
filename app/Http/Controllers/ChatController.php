<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageComment;
use App\Models\MessageReaction;
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

    private const MAX_EDITED_OFFICE_KB = 20480;

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
        $generalId = Conversation::general()?->id;

        if (! $request->boolean('list') && $request->filled('c')) {
            $selected = $conversations->firstWhere('id', (int) $request->input('c'));
            abort_unless($selected, 404);
        } elseif (! $request->boolean('list')) {
            // Par défaut, on ouvre le canal général plutôt que la conversation
            // la plus récente : c'est le point d'entrée naturel de la messagerie.
            $selected = $generalId ? $conversations->firstWhere('id', $generalId) : null;
            $selected ??= $conversations->first();
        }

        $messages = collect();
        $feedMode = false;
        $postLimit = min(100, max(10, (int) $request->query('posts', 20)));
        $hasMorePosts = false;

        if ($selected) {
            $selected->markReadBy($user);

            $feedMode = $selected->type === Conversation::TYPE_CHANNEL;

            if ($feedMode) {
                // Fil d'actualité : publications les plus récentes d'abord,
                // avec leurs mentions « J'aime » et leurs commentaires.
                $posts = $selected->messages()
                    ->with([
                        'user:id,name,job_title',
                        'reactions:id,message_id,user_id',
                        'comments' => fn ($query) => $query->with('user:id,name,job_title')->oldest('id'),
                    ])
                    ->latest('id')
                    ->limit($postLimit + 1)
                    ->get();

                $hasMorePosts = $posts->count() > $postLimit;
                $messages = $posts->take($postLimit)->values();
            } else {
                $messages = $selected->messages()
                    ->with('user:id,name,job_title')
                    ->latest('id')
                    ->limit(60)
                    ->get()
                    ->reverse()
                    ->values();
            }
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
            'feedMode' => $feedMode,
            'postLimit' => $postLimit,
            'hasMorePosts' => $hasMorePosts,
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

    /**
     * Ajoute ou retire la mention « J'aime » de l'utilisateur sur une
     * publication du fil de l'école. Réponse JSON pour la mise à jour
     * immédiate du compteur, redirection sinon.
     */
    public function toggleLike(Request $request, Message $message)
    {
        $user = $request->user();

        $this->authorizeParticipant($message->conversation, $user);

        $reaction = MessageReaction::query()
            ->where('message_id', $message->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if ($reaction) {
            $reaction->delete();
            $liked = false;
        } else {
            MessageReaction::create([
                'message_id' => $message->getKey(),
                'user_id' => $user->getKey(),
            ]);
            $liked = true;
        }

        $count = MessageReaction::query()->where('message_id', $message->getKey())->count();

        if ($request->expectsJson()) {
            return response()->json([
                'liked' => $liked,
                'count' => $count,
            ]);
        }

        return redirect()->to(route('chat.index', ['c' => $message->conversation_id]).'#post-'.$message->getKey());
    }

    /** Ajoute un commentaire sous une publication du fil de l'école. */
    public function storeComment(Request $request, Message $message): RedirectResponse
    {
        $user = $request->user();

        $this->authorizeParticipant($message->conversation, $user);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        MessageComment::create([
            'message_id' => $message->getKey(),
            'user_id' => $user->getKey(),
            'body' => $data['body'],
        ]);

        return redirect()->to(
            route('chat.index', ['c' => $message->conversation_id]).'#post-'.$message->getKey()
        );
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

    /** Affiche un PDF de la conversation dans la visionneuse intégrée. */
    public function viewPdf(Request $request, Message $message)
    {
        $this->authorizeParticipant($message->conversation, $request->user());

        abort_unless(
            $message->isPdf() && Storage::disk('local')->exists($message->attachment_path),
            404
        );

        return view('chat.pdf-view', [
            'message' => $message,
            'conversation' => $message->conversation,
        ]);
    }

    /** Ouvre un document Word ou Excel de la conversation dans l'éditeur intégré. */
    public function editDocument(Request $request, Message $message)
    {
        $this->authorizeParticipant($message->conversation, $request->user());

        abort_unless(
            $message->isEditableOffice() && Storage::disk('local')->exists($message->attachment_path),
            404
        );

        return view('chat.document-edit', [
            'message' => $message,
            'conversation' => $message->conversation,
        ]);
    }

    /** Enregistre une version modifiée et la renvoie dans la conversation. */
    public function saveDocument(Request $request, Message $message)
    {
        $user = $request->user();

        $this->authorizeParticipant($message->conversation, $user);

        $extension = $message->editableOfficeType();

        abort_unless(
            $extension !== null && Storage::disk('local')->exists($message->attachment_path),
            404
        );

        $request->validate([
            'document' => [
                'required',
                'file',
                'max:'.self::MAX_EDITED_OFFICE_KB,
                'extensions:'.$extension,
            ],
        ]);

        $file = $request->file('document');

        $this->assertValidOfficeFile($file->getRealPath(), $extension);

        $conversation = $message->conversation;

        $edited = $conversation->messages()->create([
            'user_id' => $user->getKey(),
            'body' => 'Document modifié : '.$message->attachment_name,
            'attachment_path' => $file->store('chat/'.$conversation->getKey(), 'local'),
            'attachment_name' => $message->attachment_name,
            'attachment_mime' => $file->getMimeType(),
            'attachment_size' => $file->getSize(),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();
        $conversation->markReadBy($user);

        $edited->load('user:id,name,job_title');

        app()->terminating(function () use ($edited) {
            app(ChatPushNotifier::class)->notify($edited);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $edited->id,
                'redirect' => route('chat.index', ['c' => $conversation->id]),
            ]);
        }

        return redirect()
            ->route('chat.index', ['c' => $conversation->id])
            ->with('success', 'Document modifié enregistré dans la conversation.');
    }

    /** Vérifie qu'un fichier est réellement un paquet DOCX ou XLSX. */
    private function assertValidOfficeFile(?string $path, string $extension): void
    {
        $zip = new \ZipArchive;

        $opened = $path !== null && $zip->open($path) === true;

        $requiredPart = $extension === 'xlsx' ? 'xl/workbook.xml' : 'word/document.xml';
        $valid = $opened && $zip->locateName($requiredPart) !== false;

        if ($opened) {
            $zip->close();
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'document' => $extension === 'xlsx'
                    ? 'Le fichier envoyé n’est pas un classeur Excel .xlsx valide.'
                    : 'Le fichier envoyé n’est pas un document Word .docx valide.',
            ]);
        }
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
        // Le canal « Toute l'école » est ouvert à tout compte actif : on y
        // rattache le membre à la volée, sans exiger un passage préalable
        // par la page de la messagerie (cas d'un « J'aime » ou d'un
        // commentaire envoyé depuis le fil).
        if ($conversation->type === Conversation::TYPE_CHANNEL) {
            $this->attachIfMissing($conversation, (int) $user->getKey());

            return;
        }

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
