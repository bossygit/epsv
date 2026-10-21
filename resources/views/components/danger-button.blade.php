<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 rounded-sm border border-rose-700 bg-rose-700 px-3 py-1.5 text-[11.5px] font-semibold text-white transition hover:border-rose-600 hover:bg-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-300 focus:ring-offset-1 disabled:pointer-events-none disabled:opacity-50']) }}>
    {{ $slot }}
</button>
