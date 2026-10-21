@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-sm border-line bg-paper text-[13px] text-ink shadow-none placeholder:text-gray-400 focus:border-navy focus:ring-1 focus:ring-navy/20']) }}>
