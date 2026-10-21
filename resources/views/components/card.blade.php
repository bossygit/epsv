@props(['title' => null, 'subtitle' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-line bg-paper']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-col gap-3 border-b border-line px-4 py-3 sm:flex-row sm:items-baseline sm:justify-between sm:px-6 sm:py-3.5">
            <div>
                @if ($title)
                    <h3 class="font-serif text-[16.5px] font-medium leading-tight text-ink">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-[11.5px] text-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding ? 'px-4 py-4 sm:px-6 sm:py-5' : '' }}">
        {{ $slot }}
    </div>
</div>
