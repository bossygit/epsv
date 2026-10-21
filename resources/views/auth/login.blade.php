<x-guest-layout>
    @php($profile = \App\Models\SchoolProfile::current())

    <div class="text-center">
        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full border border-gold font-serif text-[18px] text-gold">
            {{ mb_strtoupper(mb_substr($profile->short_name ?: 'E', 0, 1)) }}
        </div>
        <h1 class="mt-4 font-serif text-[25px] font-normal leading-tight text-ink">{{ $profile->short_name ?: 'EPSV' }}</h1>
        <p class="mt-1 text-[9.5px] uppercase tracking-[0.16em] text-muted">{{ $profile->name }}</p>
        <p class="mt-4 text-[12.5px] leading-relaxed text-muted">Connectez-vous pour accéder à l'espace de gestion scolaire.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4 mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-5">
        @csrf

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input id="password" class="mt-1 block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4 flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded-sm border-line text-navy shadow-none focus:ring-gold" name="remember">
                <span class="ms-2 text-[12px] text-muted">Se souvenir de moi</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-[11.5px] text-navy underline decoration-gold decoration-1 underline-offset-2 hover:text-gold" href="{{ route('password.request') }}">
                    Mot de passe oublié ?
                </a>
            @endif
        </div>

        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center rounded-sm border border-navy bg-navy px-4 py-2 text-[12.5px] font-semibold text-white transition hover:bg-navy-700 focus:outline-none focus:ring-2 focus:ring-gold/40 focus:ring-offset-1">
            Se connecter
        </button>
    </form>

    <p class="mt-5 border-t border-line pt-3 text-center text-[10.5px] text-muted">
        Accès réservé au personnel de l'établissement.
    </p>
</x-guest-layout>
