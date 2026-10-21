<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 rounded-sm border border-navy bg-navy px-3 py-1.5 text-[11.5px] font-semibold text-white transition hover:border-navy-700 hover:bg-navy-700 focus:outline-none focus:ring-2 focus:ring-gold/40 focus:ring-offset-1 disabled:pointer-events-none disabled:opacity-50']) }}>
    {{ $slot }}
</button>
