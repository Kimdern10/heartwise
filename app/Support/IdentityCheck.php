<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Stripe\Identity\VerificationSession;
use Throwable;

/**
 * "ID verified" badge with Stripe Identity: the member photographs a
 * government photo ID and takes a live selfie; Stripe checks both and
 * confirms the date of birth. Stripe charges per check (about $1.50), so it
 * is off until IDENTITY_ENABLED=true and Stripe keys are set.
 */
class IdentityCheck
{
    public static function enabled(): bool
    {
        return (bool) config('services.identity.enabled') && filled(config('cashier.secret'));
    }

    /** Start a check and return the Stripe page to send the member to. */
    public static function start(User $user, string $returnUrl): string
    {
        $session = Cashier::stripe()->identity->verificationSessions->create([
            'type' => 'document',
            'options' => ['document' => ['require_matching_selfie' => true, 'require_live_capture' => true]],
            'metadata' => ['user_id' => (string) $user->id],
            'return_url' => $returnUrl,
        ]);

        $user->forceFill(['identity_session_id' => $session->id])->save();

        return (string) $session->url;
    }

    /**
     * Ask Stripe how the member's check went (after they come back, or from the webhook).
     *
     * @return string 'verified', 'processing', 'requires_input', 'too_young' or 'none'
     */
    public static function refresh(User $user): string
    {
        if (! $user->identity_session_id) {
            return 'none';
        }

        try {
            $session = Cashier::stripe()->identity->verificationSessions->retrieve($user->identity_session_id, ['expand' => ['verified_outputs']]);
        } catch (Throwable $e) {
            report($e);

            return 'none';
        }

        return static::apply($user, $session);
    }

    public static function apply(User $user, VerificationSession $session): string
    {
        if ($session->status !== 'verified') {
            return (string) $session->status;
        }

        $dob = $session->verified_outputs->dob ?? null;
        if ($dob && $dob->year && $dob->month && $dob->day) {
            $birthday = Carbon::create((int) $dob->year, (int) $dob->month, (int) $dob->day);

            if ($birthday->age < (int) config('dating.minimum_age')) {
                $user->forceFill(['flag_reason' => 'ID check shows the member is under 18.'])->save();

                return 'too_young';
            }
        }

        $user->forceFill(['id_verified_at' => $user->id_verified_at ?? now()])->save();

        return 'verified';
    }
}
