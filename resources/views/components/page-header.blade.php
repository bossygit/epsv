@props(['title', 'subtitle' => null, 'back' => null])

<div class="mb-4 flex flex-col gap-3 border-b border-line pb-3.5 sm:flex-row sm:items-end sm:justify-between">
    <div>
        @if ($back)
            <a href="{{ $back }}" class="mb-1.5 inline-flex items-center gap-1 text-[11.5px] text-muted hover:text-gold">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Retour
            </a>
        @endif
        <h1 class="font-serif text-[24px] font-normal leading-tight tracking-[-0.01em] text-ink">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-[11.5px] text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
