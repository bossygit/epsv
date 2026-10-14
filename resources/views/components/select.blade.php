@props(['name', 'options' => [], 'selected' => null, 'placeholder' => '— Choisir —', 'required' => false])

<select
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    @if ($required) required @endif
    {{ $attributes->except(['id'])->merge(['class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm']) }}
>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $label }}</option>
    @endforeach

    {{ $slot ?? '' }}
</select>
