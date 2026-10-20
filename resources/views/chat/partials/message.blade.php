@php
    $mine = (int) $message->user_id === (int) auth()->id();
    $author = $message->user;
    $authorName = $author?->name ?? 'Compte supprimé';
@endphp

<div class="flex items-end gap-2.5 {{ $mine ? 'flex-row-reverse' : '' }}" data-message-id="{{ $message->id }}">
    <x-avatar :name="$authorName" size="sm" />

    <div class="min-w-0 max-w-[85%] sm:max-w-[75%]">
        @unless ($mine)
            <p class="mb-0.5 px-1 text-[11px] font-medium text-gray-500">
                {{ $authorName }}@if ($author?->job_title)<span class="font-normal text-gray-400"> · {{ $author->job_title }}</span>@endif
            </p>
        @endunless

        <div class="rounded-2xl px-3.5 py-2 text-sm shadow-sm {{ $mine ? 'rounded-br-md bg-indigo-600 text-white' : 'rounded-bl-md bg-white text-gray-900 ring-1 ring-gray-200' }}">
            @if ($message->trashed())
                <p class="italic opacity-70">Message supprimé</p>
            @else
                @if (filled($message->body))
                    <p class="whitespace-pre-line break-words">{!! nl2br(e($message->body)) !!}</p>
                @endif

                @if ($message->hasAttachment())
                    <div class="mt-1 flex max-w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-medium {{ $mine ? 'bg-white/10 text-white' : 'bg-gray-50 text-gray-700 ring-1 ring-gray-200' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                        </svg>
                        <span class="min-w-0 truncate">{{ $message->attachment_name }}</span>
                        @if ($message->attachmentSizeLabel())
                            <span class="shrink-0 opacity-70">{{ $message->attachmentSizeLabel() }}</span>
                        @endif
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                        @if ($message->isPdf())
                            <a href="{{ route('chat.pdf', $message) }}"
                               class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium {{ $mine ? 'bg-white/15 text-white hover:bg-white/25' : 'bg-gray-50 text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100' }}">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Voir
                            </a>
                        @endif

                        <a href="{{ route('chat.attachment', $message) }}"
                           download="{{ $message->attachment_name }}"
                           title="Télécharger {{ $message->attachment_name }}"
                           class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium {{ $mine ? 'bg-white/15 text-white hover:bg-white/25' : 'bg-gray-50 text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100' }}">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Télécharger
                        </a>

                        @if ($message->isEditableOffice())
                            <a href="{{ route('chat.documents.edit', $message) }}"
                               class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium {{ $mine ? 'bg-white/15 text-white hover:bg-white/25' : 'bg-gray-50 text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100' }}">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                                Modifier
                            </a>
                        @endif
                    </div>
                @endif
            @endif
        </div>

        <div class="mt-0.5 flex items-center gap-2 px-1 text-[11px] text-gray-400 {{ $mine ? 'justify-end' : '' }}">
            <span>{{ $message->created_at->isToday() ? $message->created_at->format('H:i') : $message->created_at->format('d/m/Y H:i') }}</span>

            @if ($mine && ! $message->trashed())
                <form method="POST" action="{{ route('chat.messages.destroy', $message) }}"
                      onsubmit="return confirm('Supprimer ce message ?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="hover:text-rose-600">Supprimer</button>
                </form>
            @endif
        </div>
    </div>
</div>
