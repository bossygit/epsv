@props(['label', 'value', 'hint' => null, 'tone' => 'indigo', 'icon' => null])

@php
    $tones = [
        'indigo' => 'bg-indigo-50 text-indigo-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
    $toneClass = $tones[$tone] ?? $tones['indigo'];
@endphp

<div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-gray-900">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $toneClass }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </div>
        @endif
    </div>
</div>
