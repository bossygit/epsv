@php
    $currentUser = auth()->user();
    $firstName = explode(' ', trim((string) $currentUser->name))[0];
    $lastPostId = $messages->first()?->id ?? 0;
@endphp

{{-- En-tête du canal --}}
<div class="flex h-14 shrink-0 items-center gap-3 border-b border-line bg-paper px-3 sm:px-4">
    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-sm border border-gold/60 bg-gold/10 text-gold">
        <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" />
        </svg>
    </div>
    <div class="min-w-0">
        <p class="truncate text-[13px] font-semibold text-ink">Toute l'école</p>
        <p class="truncate text-[11px] text-muted">Fil d'actualité · visible par tout le personnel</p>
    </div>
</div>

<div class="flex-1 overflow-y-auto bg-ivory"
     x-data="feedRefresh(@js($lastPostId), @js(route('chat.messages.poll', $conversation)))">
    <div class="mx-auto w-full max-w-[680px] space-y-3 px-3 py-4 sm:px-4 sm:py-5">

        {{-- Nouvelle publication --}}
        <div class="rounded-sm border border-line bg-paper p-3 sm:p-4">
            <form method="POST" action="{{ route('chat.messages.store', $conversation) }}" enctype="multipart/form-data">
                @csrf

                <div class="flex items-start gap-3">
                    <x-avatar :name="$currentUser->name" size="md" />

                    <div class="min-w-0 flex-1">
                        <textarea name="body"
                                  rows="2"
                                  maxlength="4000"
                                  placeholder="Quoi de neuf, {{ $firstName }} ? Une information à partager avec toute l'école…"
                                  class="block w-full resize-none rounded-sm border-line bg-ivory text-[13px] text-ink placeholder:text-gray-400 focus:border-navy focus:bg-paper focus:ring-1 focus:ring-navy/20"
                                  @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 200) + 'px'"></textarea>

                        <div class="mt-2 flex items-center justify-between gap-2">
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-sm px-1.5 py-1 text-[11.5px] font-medium text-muted hover:bg-ivory">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                </svg>
                                Joindre un fichier
                                <input type="file" name="attachment" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                            </label>

                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-sm bg-navy px-3.5 py-1.5 text-[11.5px] font-semibold text-white hover:bg-navy-700">
                                Publier
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Nouvelles publications détectées par l'interrogation périodique --}}
        <div x-show="hasNews" x-cloak class="flex justify-center">
            <button type="button" @click="reload()"
                    class="inline-flex items-center gap-1.5 rounded-sm border border-gold bg-gold-pale px-3 py-1.5 text-[11.5px] font-semibold text-ink hover:bg-gold/20">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m0-15l-4.5 4.5M12 4.5l4.5 4.5" />
                </svg>
                Nouvelles publications — actualiser
            </button>
        </div>

        @forelse ($messages as $message)
            @include('chat.partials.post', ['message' => $message, 'currentUser' => $currentUser])
        @empty
            <div class="rounded-sm border border-line bg-paper px-6 py-12 text-center">
                <p class="font-serif text-[16px] text-ink">Le fil est encore vide</p>
                <p class="mt-1 text-[12px] text-muted">Publiez la première information de l'école.</p>
            </div>
        @endforelse

        @if ($hasMorePosts)
            <div class="pt-1 text-center">
                <a href="{{ route('chat.index', ['c' => $conversation->id, 'posts' => min(100, $postLimit + 20)]) }}"
                   class="text-[11.5px] font-semibold text-navy underline decoration-gold decoration-1 underline-offset-2 hover:text-gold">
                    Afficher les publications plus anciennes
                </a>
            </div>
        @endif
    </div>
</div>

@push('scripts')
    <script>
        window.feedRefresh = function (lastId, pollUrl) {
            return {
                lastId: parseInt(lastId || '0', 10),
                hasNews: false,
                timer: null,

                init() {
                    this.timer = setInterval(() => this.check(), 20000);
                },

                destroy() {
                    clearInterval(this.timer);
                },

                async check() {
                    if (document.hidden || !pollUrl) {
                        return;
                    }

                    try {
                        const response = await fetch(pollUrl + '?after_id=' + this.lastId, {
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const data = await response.json();

                        if (data.last_id > this.lastId) {
                            this.hasNews = true;
                            this.lastId = data.last_id;
                        }

                        if (typeof window.epsvUpdateUnread === 'function') {
                            window.epsvUpdateUnread(data.unread_total);
                        }
                    } catch (error) {
                        // Réseau instable : nouvelle tentative au prochain cycle.
                    }
                },

                reload() {
                    window.location.reload();
                },
            };
        };
    </script>
@endpush
