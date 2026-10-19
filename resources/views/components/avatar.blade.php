@props(['name' => '?', 'size' => 'md'])

@php
    $initials = collect(preg_split('/\s+/u', trim((string) $name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $palette = [
        'bg-indigo-600',
        'bg-emerald-600',
        'bg-amber-500',
        'bg-rose-500',
        'bg-sky-600',
        'bg-violet-600',
    ];

    $color = $palette[abs(crc32((string) $name)) % count($palette)];

    $sizes = [
        'sm' => 'h-8 w-8 text-[11px]',
        'md' => 'h-9 w-9 text-xs',
        'lg' => 'h-10 w-10 text-sm',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white '.$color.' '.($sizes[$size] ?? $sizes['md'])]) }}>
    {{ $initials ?: '?' }}
</span>
