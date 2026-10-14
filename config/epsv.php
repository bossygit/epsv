<?php

/*
|--------------------------------------------------------------------------
| Configuration EPSV
|--------------------------------------------------------------------------
| Ces valeurs passent par config() (et non env()) afin de rester valides
| lorsque la configuration est mise en cache en production
| (php artisan config:cache).
*/

return [
    // Créer les données de démonstration lors du seeding (à désactiver en production réelle).
    'seed_demo' => env('EPSV_SEED_DEMO', true),

    // Mot de passe initial des comptes créés par les seeders.
    'demo_password' => env('EPSV_DEMO_PASSWORD', 'Epsv@2026'),

    // Compte direction créé par le seeder.
    'admin_email' => env('EPSV_ADMIN_EMAIL', 'direction@epsv.cg'),
];
