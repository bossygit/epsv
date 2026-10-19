<?php

namespace App\Services;

use App\Models\Message;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envoie une notification Web Push aux autres participants d'une conversation.
 *
 * Appelé après l'envoi de la réponse HTTP (app()->terminating) pour ne pas
 * ralentir l'expéditeur. Les abonnements expirés (404/410) sont supprimés.
 */
class ChatPushNotifier
{
    public function notify(Message $message): void
    {
        if (! config('webpush.enabled')) {
            return;
        }

        $message->loadMissing(['user:id,name', 'conversation']);

        $conversation = $message->conversation;

        if (! $conversation) {
            return;
        }

        $recipientIds = $conversation->participants()
            ->where('users.id', '<>', $message->user_id)
            ->where('users.is_active', true)
            ->pluck('users.id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $recipientIds)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $senderName = $message->user?->name ?? 'Un membre';
        $body = $message->body ?: 'Pièce jointe';
        $title = $conversation->type === \App\Models\Conversation::TYPE_DIRECT
            ? $senderName
            : ($conversation->name ?: 'Messagerie EPSV');

        $payload = json_encode([
            'title' => $title,
            'body' => $senderName.' : '.Str::limit($body, 140),
            'url' => route('chat.index', ['c' => $conversation->id]),
            'tag' => 'epsv-chat-'.$conversation->id,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('webpush.subject'),
                    'publicKey' => config('webpush.public_key'),
                    'privateKey' => config('webpush.private_key'),
                ],
            ], ['TTL' => 3600, 'urgency' => 'high']);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                    ]),
                    $payload,
                );
            }

            foreach ($webPush->flush() as $report) {
                $stored = $subscriptions->first(
                    fn (PushSubscription $subscription) => $subscription->endpoint_hash === PushSubscription::hashEndpoint($report->getEndpoint())
                );

                if (! $stored) {
                    continue;
                }

                if ($report->isSuccess()) {
                    $stored->forceFill(['last_used_at' => now()])->save();
                } elseif ($report->isSubscriptionExpired()) {
                    $stored->delete();
                } else {
                    Log::warning('Échec d\'envoi d\'une notification push', [
                        'endpoint' => $report->getEndpoint(),
                        'reason' => $report->getReason(),
                        'status' => $report->getResponse()?->getStatusCode(),
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            // Une panne de push ne doit jamais faire échouer l'envoi du message.
            Log::warning('Notifications push indisponibles : '.$exception->getMessage());
        }
    }
}
