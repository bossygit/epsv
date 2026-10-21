@props(['name', 'options' => [], 'selected' => null, 'placeholder' => '— Choisir —', 'required' => false])

<select
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    @if ($required) required @endif
    {{ $attributes->except(['id'])->merge(['class' => 'block w-full rounded-sm border-line bg-paper text-[13px] text-ink focus:border-navy focus:ring-1 focus:ring-navy/20']) }}
>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $label }}</option>
    @endforeach

    {{ $slot ?? '' }}
</select>
