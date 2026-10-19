<?php

/*
|--------------------------------------------------------------------------
| Notifications Web Push (VAPID)
|--------------------------------------------------------------------------
| Désactivées tant que les clés VAPID ne sont pas renseignées. Les clés se
| génèrent avec `php artisan webpush:vapid` puis se placent dans .env.
*/

return [
    'enabled' => (bool) env('WEBPUSH_ENABLED', false)
        && filled(env('VAPID_PUBLIC_KEY'))
        && filled(env('VAPID_PRIVATE_KEY')),

    'public_key' => env('VAPID_PUBLIC_KEY'),

    'private_key' => env('VAPID_PRIVATE_KEY'),

    'subject' => env('VAPID_SUBJECT', 'mailto:contact@epsv.cg'),
];
