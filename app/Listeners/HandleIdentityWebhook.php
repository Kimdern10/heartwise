<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\IdentityCheck;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Stripe tells us when an ID check finishes (identity.verification_session.*),
 * even if the member closed the page before coming back.
 */
class HandleIdentityWebhook
{
    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? '';

        if (! str_starts_with($type, 'identity.verification_session.')) {
            return;
        }

        $object = $event->payload['data']['object'] ?? [];
        $userId = $object['metadata']['user_id'] ?? null;
        $user = is_numeric($userId) ? User::find((int) $userId) : null;

        if (! $user || ($object['id'] ?? null) !== $user->identity_session_id) {
            return;
        }

        if ($type === 'identity.verification_session.verified') {
            // Re-read from Stripe so the date of birth is checked too.
            IdentityCheck::refresh($user);
        }
    }
}
