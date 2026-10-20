<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Un utilisateur déjà connecté qui repasse par une page invitée
        // (login, mot de passe oublié…) atterrit dans la messagerie.
        RedirectIfAuthenticated::redirectUsing(fn () => route('chat.index'));
    }
}
