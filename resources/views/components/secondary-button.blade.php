<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-1.5 rounded-sm border border-line bg-paper px-3 py-1.5 text-[11.5px] font-semibold text-navy transition hover:border-navy hover:bg-ivory focus:outline-none focus:ring-2 focus:ring-gold/40 focus:ring-offset-1 disabled:pointer-events-none disabled:opacity-50']) }}>
    {{ $slot }}
</button>
