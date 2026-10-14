@props(['title' => null, 'subtitle' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                @if ($title)
                    <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-gray-500">{{ $subtitle }}</p>
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
