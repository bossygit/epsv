@php
    $currentUser = auth()->user();

    $selectedTitle = $selected?->titleFor($currentUser);
    $selectedSubtitle = null;

    if ($selected) {
        if ($selected->type === \App\Models\Conversation::TYPE_CHANNEL) {
            $selectedSubtitle = 'Canal général — tout le personnel';
        } elseif ($selected->type === \App\Models\Conversation::TYPE_GROUP) {
            $selectedSubtitle = $selected->participants->count().' participant(s)';
        } else {
            $other = $selected->participants->first(fn ($participant) => $participant->id !== $currentUser->id);
            $selectedSubtitle = $other?->job_title ?: 'Conversation privée';
        }
    }

    $mobileThread = $selected && request()->filled('c') && ! request()->boolean('list');

    $colleagueOptions = $colleagues
        ->mapWithKeys(fn ($colleague) => [
            $colleague->id => $colleague->name.($colleague->job_title ? ' — '.$colleague->job_title : ''),
        ]);
@endphp

<x-app-layout>
    <x-page-header
        title="Messagerie"
        subtitle="Échanges internes entre les membres du personnel — canal général, groupes et conversations directes."
    />

    <div
        x-data="chatApp($el)"
        data-conversation-id="{{ $selected?->id }}"
        data-last-id="{{ $messages->last()?->id ?? 0 }}"
        data-poll-url="{{ $selected ? route('chat.messages.poll', $selected) : '' }}"
        data-send-url="{{ $selected ? route('chat.messages.store', $selected) : '' }}"
        data-mobile-thread="{{ $mobileThread ? '1' : '0' }}"
        class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200"
    >
        <div class="flex h-[calc(100vh-14rem)] min-h-[480px]">
            {{-- Liste des conversations --}}
            <aside class="w-full shrink-0 flex-col border-r border-gray-200 md:flex md:w-80"
                   :class="mobileThread ? 'hidden md:flex' : 'flex'">
                <div class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-gray-200 px-3">
                    <p class="text-sm font-semibold text-gray-900">Conversations</p>
                    <button type="button"
                            @click="$dispatch('open-modal', 'new-conversation')"
                            class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Nouvelle
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-2">
                    @forelse ($conversations as $conversation)
                        @php
                            $last = $conversation->lastMessage;
                            $preview = $last?->body
                                ? \Illuminate\Support\Str::limit($last->body, 42)
                                : ($last?->hasAttachment() ? 'Pièce jointe' : 'Aucun message');
                            $count = $unread[$conversation->id] ?? 0;
                            $isActive = $selected?->id === $conversation->id;
                        @endphp

                        <a href="{{ route('chat.index', ['c' => $conversation->id]) }}"
                           class="flex items-start gap-3 rounded-lg px-2.5 py-2.5 {{ $isActive ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                            <x-avatar :name="$conversation->titleFor($currentUser)" />

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="truncate text-sm font-semibold {{ $isActive ? 'text-indigo-900' : 'text-gray-900' }}">
                                        {{ $conversation->titleFor($currentUser) }}
                                    </p>
                                    @if ($last)
                                        <span class="shrink-0 text-[11px] text-gray-400">
                                            {{ $last->created_at->isToday() ? $last->created_at->format('H:i') : $last->created_at->format('d/m') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-0.5 flex items-center justify-between gap-2">
                                    <p class="truncate text-xs text-gray-500">{{ $preview }}</p>
                                    @if ($count > 0)
                                        <span class="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                                            {{ $count > 99 ? '99+' : $count }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <p class="px-3 py-6 text-center text-sm text-gray-500">Aucune conversation pour le moment.</p>
                    @endforelse
                </div>
            </aside>

            {{-- Fil de discussion --}}
            <section class="min-w-0 flex-1 flex-col" :class="mobileThread ? 'flex' : 'hidden md:flex'">
                @if ($selected)
                    <div class="flex h-14 shrink-0 items-center gap-3 border-b border-gray-200 px-3 sm:px-4">
                        <a href="{{ route('chat.index', ['list' => 1]) }}"
                           class="-ml-1 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 md:hidden"
                           title="Retour aux conversations">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>

                        <x-avatar :name="$selectedTitle" />

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $selectedTitle }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $selectedSubtitle }}</p>
                        </div>
                    </div>

                    <div x-ref="messages" class="flex-1 space-y-4 overflow-y-auto bg-gray-50 px-3 py-4 sm:px-4">
                        @forelse ($messages as $message)
                            @include('chat.partials.message', ['message' => $message])
                        @empty
                            <div id="chat-empty" class="flex h-full items-center justify-center text-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Aucun message pour le moment.</p>
                                    <p class="mt-1 text-xs text-gray-400">Écrivez le premier message de cette conversation.</p>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <form x-ref="form"
                          method="POST"
                          action="{{ route('chat.messages.store', $selected) }}"
                          enctype="multipart/form-data"
                          @submit.prevent="send"
                          class="shrink-0 border-t border-gray-200 bg-white px-3 py-3 sm:px-4">
                        @csrf

                        <div class="flex items-end gap-2">
                            <button type="button"
                                    @click="$refs.file.click()"
                                    class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                                    title="Joindre un fichier">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                </svg>
                            </button>

                            <input x-ref="file"
                                   type="file"
                                   name="attachment"
                                   class="sr-only"
                                   accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                                   @change="fileName = $event.target.files[0]?.name || ''">

                            <div class="min-w-0 flex-1">
                                <textarea name="body"
                                          rows="1"
                                          maxlength="4000"
                                          placeholder="Écrire un message… (Entrée pour envoyer, Maj+Entrée pour aller à la ligne)"
                                          class="block max-h-40 w-full resize-none rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                                          @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send(); }"></textarea>

                                <p x-show="fileName" x-text="fileName" class="mt-1 truncate text-[11px] text-gray-500"></p>
                            </div>

                            <button type="submit"
                                    :disabled="sending"
                                    class="rounded-lg bg-indigo-600 p-2.5 text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    title="Envoyer">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                </svg>
                            </button>
                        </div>

                        <p x-show="error" x-text="error" class="mt-1 text-xs text-rose-600"></p>
                    </form>
                @else
                    <div class="flex flex-1 items-center justify-center p-8 text-center">
                        <div>
                            <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                            </svg>
                            <p class="mt-3 text-sm font-medium text-gray-700">Sélectionnez une conversation</p>
                            <p class="mt-1 text-xs text-gray-500">ou créez-en une nouvelle pour commencer à échanger.</p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- Nouvelle conversation --}}
    <x-modal name="new-conversation" maxWidth="md" focusable>
        <form method="POST" action="{{ route('chat.conversations.store') }}" class="p-6" x-data="{ mode: @js(old('type', 'direct')) }">
            @csrf
            <input type="hidden" name="type" :value="mode">

            <h3 class="text-base font-semibold text-gray-900">Nouvelle conversation</h3>
            <p class="mt-1 text-sm text-gray-500">Échangez en privé avec un collègue ou créez un groupe de travail.</p>

            <div class="mt-4 grid grid-cols-2 gap-1 rounded-lg bg-gray-100 p-1">
                <button type="button" @click="mode = 'direct'"
                        :class="mode === 'direct' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="rounded-md px-3 py-1.5 text-sm font-medium">
                    Message direct
                </button>
                <button type="button" @click="mode = 'group'"
                        :class="mode === 'group' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="rounded-md px-3 py-1.5 text-sm font-medium">
                    Groupe
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <div x-show="mode === 'direct'">
                    <x-input-label for="chat-user" value="Destinataire" />
                    <x-select id="chat-user" name="user_id" :options="$colleagueOptions" placeholder="— Choisir un collègue —" class="mt-1" />
                </div>

                <div x-show="mode === 'group'" x-cloak class="space-y-4">
                    <div>
                        <x-input-label for="chat-name" value="Nom du groupe" />
                        <x-text-input id="chat-name" name="name" type="text" maxlength="80" class="mt-1 block w-full" placeholder="Ex. Conseil de classe 6ème A" />
                    </div>

                    <div>
                        <x-input-label value="Participants" />
                        @if ($colleagues->isEmpty())
                            <p class="mt-1 text-sm text-gray-500">Aucun autre compte actif pour le moment.</p>
                        @else
                            <div class="mt-1 max-h-48 space-y-0.5 overflow-y-auto rounded-md border border-gray-300 p-1.5">
                                @foreach ($colleagues as $colleague)
                                    <label class="flex items-center gap-2 rounded px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                        <input type="checkbox"
                                               name="user_ids[]"
                                               value="{{ $colleague->id }}"
                                               @checked(in_array($colleague->id, old('user_ids', [])))
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="truncate">{{ $colleague->name }}</span>
                                        @if ($colleague->job_title)
                                            <span class="truncate text-xs text-gray-400">{{ $colleague->job_title }}</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button type="button" @click="$dispatch('close-modal', 'new-conversation')">
                    Annuler
                </x-secondary-button>
                <x-primary-button>
                    Créer la conversation
                </x-primary-button>
            </div>
        </form>
    </x-modal>

    @push('scripts')
        <script>
            window.chatApp = function (el) {
                const dataset = el.dataset;

                return {
                    conversationId: dataset.conversationId ? parseInt(dataset.conversationId, 10) : null,
                    lastId: parseInt(dataset.lastId || '0', 10),
                    pollUrl: dataset.pollUrl || null,
                    sendUrl: dataset.sendUrl || null,
                    mobileThread: dataset.mobileThread === '1',
                    sending: false,
                    polling: false,
                    error: '',
                    fileName: '',
                    timer: null,
                    onVisibilityChange: null,

                    init() {
                        this.scrollToBottom(false);

                        if (!this.conversationId) {
                            return;
                        }

                        this.timer = setInterval(() => this.poll(), 4000);
                        this.onVisibilityChange = () => {
                            if (!document.hidden) {
                                this.poll();
                            }
                        };
                        document.addEventListener('visibilitychange', this.onVisibilityChange);
                    },

                    destroy() {
                        clearInterval(this.timer);
                        if (this.onVisibilityChange) {
                            document.removeEventListener('visibilitychange', this.onVisibilityChange);
                        }
                    },

                    nearBottom() {
                        const el = this.$refs.messages;
                        return el ? el.scrollHeight - el.scrollTop - el.clientHeight < 120 : true;
                    },

                    scrollToBottom(smooth = true) {
                        const el = this.$refs.messages;
                        if (!el) {
                            return;
                        }
                        el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
                    },

                    removeEmptyState() {
                        document.getElementById('chat-empty')?.remove();
                    },

                    /** Ajoute les messages absents du DOM (évite les doublons si deux requêtes se croisent). */
                    appendMessages(html) {
                        if (!html) {
                            return;
                        }

                        const template = document.createElement('template');
                        template.innerHTML = html;

                        template.content.querySelectorAll('[data-message-id]').forEach((node) => {
                            const id = node.dataset.messageId;
                            if (id && !document.querySelector(`[data-message-id="${id}"]`)) {
                                this.$refs.messages.appendChild(node);
                            }
                        });
                    },

                    setUnreadBadge(total) {
                        const badge = document.getElementById('chat-unread-badge');
                        if (!badge) {
                            return;
                        }
                        badge.textContent = total > 99 ? '99+' : total;
                        badge.classList.toggle('hidden', !total);
                    },

                    async poll() {
                        if (!this.pollUrl || document.hidden || this.sending || this.polling) {
                            return;
                        }

                        this.polling = true;

                        try {
                            const response = await fetch(this.pollUrl + '?after_id=' + this.lastId, {
                                headers: { 'Accept': 'application/json' },
                            });

                            if (!response.ok) {
                                return;
                            }

                            const data = await response.json();

                            if (data.html) {
                                const stick = this.nearBottom();
                                this.appendMessages(data.html);
                                this.lastId = Math.max(this.lastId, data.last_id);
                                this.removeEmptyState();

                                if (stick) {
                                    this.scrollToBottom();
                                }
                            }

                            this.setUnreadBadge(data.unread_total);
                        } catch (error) {
                            // Réseau instable : une nouvelle tentative aura lieu au prochain cycle.
                        } finally {
                            this.polling = false;
                        }
                    },

                    async send() {
                        if (!this.sendUrl || this.sending) {
                            return;
                        }

                        this.error = '';

                        const form = this.$refs.form;
                        const textarea = form.querySelector('textarea[name="body"]');
                        const fileInput = form.querySelector('input[type="file"]');

                        if (!textarea.value.trim() && !fileInput.files.length) {
                            this.error = 'Écrivez un message ou joignez un fichier.';
                            return;
                        }

                        this.sending = true;

                        try {
                            const response = await fetch(this.sendUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: new FormData(form),
                            });

                            const data = await response.json();

                            if (!response.ok) {
                                this.error = data.message
                                    || (data.errors ? Object.values(data.errors)[0][0] : 'Envoi impossible.');
                                return;
                            }

                            this.appendMessages(data.html);
                            this.lastId = Math.max(this.lastId, data.last_id);
                            this.removeEmptyState();

                            form.reset();
                            textarea.style.height = 'auto';
                            this.fileName = '';
                            this.scrollToBottom();
                        } catch (error) {
                            this.error = 'Connexion interrompue. Réessayez.';
                        } finally {
                            this.sending = false;
                        }
                    },
                };
            };
        </script>
    @endpush
</x-app-layout>
