@props(['label', 'value', 'hint' => null, 'tone' => 'indigo', 'icon' => null, 'variant' => 'card'])

@php
    $dots = [
        'indigo' => 'bg-navy',
        'emerald' => 'bg-emerald-600',
        'amber' => 'bg-amber-600',
        'rose' => 'bg-rose-600',
        'slate' => 'bg-slate-400',
    ];
    $dotClass = $dots[$tone] ?? $dots['indigo'];

    $valueTones = [
        'indigo' => 'text-ink',
        'emerald' => 'text-ink',
        'amber' => 'text-amber-700',
        'rose' => 'text-rose-700',
        'slate' => 'text-ink',
    ];
    $valueClass = $valueTones[$tone] ?? $valueTones['indigo'];
@endphp

@if ($variant === 'strip')
    {{-- Cellule du bandeau d'indicateurs (tableau de bord) --}}
    <div {{ $attributes->merge(['class' => 'flex flex-col gap-0.5 bg-paper px-4 py-3 sm:px-5']) }}>
        <p class="flex items-center gap-1.5 text-[9.5px] font-semibold uppercase tracking-[0.12em] text-muted">
            <span class="inline-block h-1 w-1 rounded-full {{ $dotClass }}"></span>
            {{ $label }}
        </p>
        <p class="num text-[23px] font-semibold leading-tight tracking-[-0.02em] {{ $valueClass }}">{{ $value }}</p>
        @if ($hint)
            <p class="text-[10.5px] text-muted">{{ $hint }}</p>
        @endif
    </div>
@else
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-line bg-paper p-5']) }}>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">{{ $label }}</p>
                <p class="num mt-1.5 font-serif text-[30px] font-normal leading-none tracking-[-0.02em] {{ $valueClass }}">{{ $value }}</p>
                @if ($hint)
                    <p class="mt-1.5 text-[11.5px] text-muted">{{ $hint }}</p>
                @endif
            </div>
            @if ($icon)
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-sm border border-line bg-ivory text-navy">
                    <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                    </svg>
                </div>
            @endif
        </div>
    </div>
@endif
