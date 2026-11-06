<?php

namespace App\Rules;

use App\Models\Setting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses throwaway email addresses (mailinator.com and friends), including
 * their subdomains, plus any extra domains an admin has blocked.
 */
class NotDisposableEmail implements ValidationRule
{
    public static function isDisposable(string $email): bool
    {
        $domain = strtolower(trim((string) substr(strrchr($email, '@') ?: '', 1)));

        if ($domain === '') {
            return false;
        }

        $blocked = array_merge(config('disposable_email_domains'), array_map('strtolower', Setting::list('blocked_email_domains', false)));

        foreach ($blocked as $bad) {
            if ($domain === $bad || str_ends_with($domain, '.'.$bad)) {
                return true;
            }
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isDisposable($value)) {
            $fail(__('Please use your real, permanent email address — temporary email services aren’t allowed.'));
        }
    }
}
