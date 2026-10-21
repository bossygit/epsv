@php
    $mine = (int) $message->user_id === (int) $currentUser->id;
    $author = $message->user;
    $authorName = $author?->name ?? 'Compte supprimé';
    $liked = $message->isLikedBy($currentUser);
    $likeCount = $message->reactions->count();
    $comments = $message->comments;
    $isImage = $message->hasAttachment() && str_starts_with((string) $message->attachment_mime, 'image/');
    $when = $message->created_at->isToday()
        ? "Aujourd'hui à ".$message->created_at->format('H:i')
        : $message->created_at->format('d/m/Y à H:i');
@endphp

<article id="post-{{ $message->id }}"
         class="rounded-lg border border-line bg-paper"
         x-data="{
             liked: {{ $liked ? 'true' : 'false' }},
             count: {{ $likeCount }},
             busy: false,
             async toggleLike() {
                 if (this.busy) return;
                 this.busy = true;
                 try {
                     const response = await fetch('{{ route('chat.messages.like', $message) }}', {
                         method: 'POST',
                         headers: {
                             'Accept': 'application/json',
                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                         },
                     });
                     if (response.ok) {
                         const data = await response.json();
                         this.liked = data.liked;
                         this.count = data.count;
                     }
                 } catch (error) {
                     // Silencieux : l'utilisateur peut réessayer.
                 } finally {
                     this.busy = false;
                 }
             },
         }">

    {{-- Auteur --}}
    <div class="flex items-start gap-3 px-3 pt-3 sm:px-4">
        <x-avatar :name="$authorName" size="md" />

        <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-semibold leading-tight text-ink">{{ $authorName }}</p>
            <p class="truncate text-[11px] leading-tight text-muted">
                {{ $author?->job_title ?: 'Personnel de l\'école' }} · {{ $when }}
            </p>
        </div>

        @if ($mine && ! $message->trashed())
            <form method="POST" action="{{ route('chat.messages.destroy', $message) }}"
                  onsubmit="return confirm('Supprimer cette publication ?')">
                @csrf
                @method('DELETE')
                <button type="submit" title="Supprimer"
                        class="rounded-sm p-1 text-gray-400 hover:bg-ivory hover:text-rose-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </button>
            </form>
        @endif
    </div>

    {{-- Contenu --}}
    @if ($message->trashed())
        <p class="px-3 py-3 text-[13px] italic text-muted sm:px-4">Publication supprimée</p>
    @elseif (filled($message->body))
        <div class="whitespace-pre-line break-words px-3 py-2.5 text-[13.5px] leading-relaxed text-ink sm:px-4">{{ $message->body }}</div>
    @endif

    {{-- Pièce jointe --}}
    @if ($message->hasAttachment() && ! $message->trashed())
        @if ($isImage)
            <a href="{{ route('chat.attachment', $message) }}" target="_blank" rel="noopener"
               class="block border-y border-line-soft bg-ivory">
                <img src="{{ route('chat.attachment', $message) }}"
                     alt="{{ $message->attachment_name }}"
                     class="max-h-[440px] w-full object-contain">
            </a>
        @else
            <div class="mx-3 mb-3 mt-1 flex flex-wrap items-center gap-2 rounded-sm border border-line bg-ivory px-2.5 py-2 sm:mx-4">
                <svg class="h-4 w-4 shrink-0 text-navy" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <span class="min-w-0 flex-1 truncate text-[11.5px] font-medium text-ink">{{ $message->attachment_name }}</span>
                @if ($message->attachmentSizeLabel())
                    <span class="shrink-0 text-[10.5px] text-muted">{{ $message->attachmentSizeLabel() }}</span>
                @endif

                @if ($message->isPdf())
                    <a href="{{ route('chat.pdf', $message) }}"
                       class="shrink-0 rounded-sm border border-line bg-paper px-2 py-1 text-[10.5px] font-semibold text-navy hover:border-navy">Voir</a>
                @endif

                <a href="{{ route('chat.attachment', $message) }}" download="{{ $message->attachment_name }}"
                   class="shrink-0 rounded-sm border border-line bg-paper px-2 py-1 text-[10.5px] font-semibold text-navy hover:border-navy">Télécharger</a>

                @if ($message->isEditableOffice())
                    <a href="{{ route('chat.documents.edit', $message) }}"
                       class="shrink-0 rounded-sm border border-line bg-paper px-2 py-1 text-[10.5px] font-semibold text-navy hover:border-navy">Modifier</a>
                @endif
            </div>
        @endif
    @endif

    {{-- Compteurs --}}
    <div class="flex items-center justify-between gap-2 px-3 pb-1.5 pt-1 text-[11px] text-muted sm:px-4">
        <span x-show="count > 0" x-cloak>
            <span x-text="count"></span> J'aime
        </span>
        <span x-show="count === 0" x-cloak></span>
        <span>{{ $comments->count() }} commentaire{{ $comments->count() > 1 ? 's' : '' }}</span>
    </div>

    {{-- Actions --}}
    <div class="grid grid-cols-2 border-t border-line-soft">
        <button type="button" @click="toggleLike()"
                class="flex items-center justify-center gap-1.5 py-2 text-[11.5px] font-semibold transition hover:bg-ivory"
                :class="liked ? 'text-navy' : 'text-muted hover:text-navy'">
            <svg class="h-4 w-4" :fill="liked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 012.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 00.322-1.672V3a.75.75 0 01.75-.75 2.25 2.25 0 012.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 01-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 00-1.423-.23H5.904M14.25 9h2.25M5.904 18.75c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 01-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 00-1.302 4.665c0 1.194.232 2.333.654 3.625z" />
            </svg>
            <span x-text="liked ? 'Je n\'aime plus' : 'J\'aime'">J'aime</span>
        </button>

        <button type="button" @click="$refs.commentInput.focus()"
                class="flex items-center justify-center gap-1.5 border-l border-line-soft py-2 text-[11.5px] font-semibold text-muted transition hover:bg-ivory hover:text-navy">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
            </svg>
            Commenter
        </button>
    </div>

    {{-- Commentaires --}}
    @if ($comments->isNotEmpty())
        <div class="space-y-2 border-t border-line-soft bg-ivory px-3 py-2.5 sm:px-4">
            @foreach ($comments as $comment)
                <div class="flex items-start gap-2">
                    <x-avatar :name="$comment->user?->name ?? '?'" size="sm" />
                    <div class="min-w-0 flex-1 rounded-sm border border-line bg-paper px-2.5 py-1.5">
                        <p class="text-[11px] font-semibold leading-tight text-ink">
                            {{ $comment->user?->name ?? 'Compte supprimé' }}
                            <span class="ml-1 font-normal text-muted">
                                {{ $comment->created_at->isToday() ? $comment->created_at->format('H:i') : $comment->created_at->format('d/m/Y') }}
                            </span>
                        </p>
                        <p class="whitespace-pre-line break-words text-[12px] leading-snug text-ink">{{ $comment->body }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Nouveau commentaire --}}
    <form method="POST" action="{{ route('chat.messages.comments.store', $message) }}"
          class="flex items-center gap-2 border-t border-line-soft px-3 py-2 sm:px-4">
        @csrf
        <x-avatar :name="$currentUser->name" size="sm" />
        <input type="text" name="body" x-ref="commentInput" maxlength="2000" required
               placeholder="Écrire un commentaire…"
               class="h-8 min-w-0 flex-1 rounded-sm border-line bg-ivory text-[12px] text-ink placeholder:text-gray-400 focus:border-navy focus:bg-paper focus:ring-1 focus:ring-navy/20">
        <button type="submit"
                class="shrink-0 rounded-sm border border-line bg-paper px-2.5 py-1 text-[11px] font-semibold text-navy hover:border-navy">
            Envoyer
        </button>
    </form>
</article>
