<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enregistrement des abonnements Web Push des navigateurs.
 * Un utilisateur peut avoir plusieurs appareils ; l'endpoint identifie
 * l'abonnement et peut être réattribué si un autre compte se connecte
 * sur le même navigateur.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! config('webpush.enabled')) {
            return response()->json([
                'message' => 'Les notifications push ne sont pas configurées sur ce serveur.',
            ], 503);
        }

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048', 'url'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $user = $request->user();
        $endpoint = $data['endpoint'];

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($endpoint)],
            [
                'user_id' => $user->getKey(),
                'endpoint' => $endpoint,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ],
        );

        return response()->json(['ok' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        PushSubscription::query()
            ->where('user_id', $request->user()->getKey())
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
