<?php

namespace App\Support;

use App\Models\Profile;

/**
 * A simple, explainable 1–99 compatibility score between two members,
 * based on goals, family plans, shared interests and each other's preferences.
 */
class Compatibility
{
    public static function score(Profile $me, Profile $them): int
    {
        $score = 40;

        if ($me->relationship_goal && $me->relationship_goal === $them->relationship_goal) {
            $score += 15;
        }

        $score += self::familyPlans($me->wants_kids, $them->wants_kids);

        $shared = count(array_intersect($me->interests ?? [], $them->interests ?? []));
        $score += min(16, $shared * 4);

        foreach (['religion', 'drinking', 'smoking', 'has_kids', 'wants_kids'] as $key) {
            $wanted = $me->preferred($key);

            if ($wanted && $them->{$key}) {
                $score += in_array($them->{$key}, $wanted, true) ? 5 : -8;
            }
        }

        $theirAge = $them->user?->age();
        $myAge = $me->user?->age();

        if ($theirAge && $me->pref_age_min && $me->pref_age_max) {
            $score += ($theirAge >= $me->pref_age_min && $theirAge <= $me->pref_age_max) ? 8 : -10;
        }

        if ($myAge && $them->pref_age_min && $them->pref_age_max) {
            $score += ($myAge >= $them->pref_age_min && $myAge <= $them->pref_age_max) ? 5 : -5;
        }

        $miles = $me->milesFrom($them);
        if ($miles !== null && $me->pref_max_miles) {
            $score += $miles <= $me->pref_max_miles ? 5 : -5;
        }

        $score += $them->photo_verified_at ? 3 : 0;
        $score += $them->location_verified_at ? 2 : 0;

        return max(1, min(99, $score));
    }

    /**
     * Shared reasons to show on a profile ("You both want children").
     *
     * @return list<string>
     */
    public static function reasons(Profile $me, Profile $them): array
    {
        $reasons = [];

        if ($me->relationship_goal && $me->relationship_goal === $them->relationship_goal) {
            $reasons[] = __('You’re both looking for: :goal', ['goal' => strtolower($me->relationship_goal->label())]);
        }

        if ($me->wants_kids && $me->wants_kids === $them->wants_kids) {
            $reasons[] = config('profile_options.vitals.wants_kids.options.'.$me->wants_kids);
        }

        $shared = array_values(array_intersect($me->interests ?? [], $them->interests ?? []));
        if ($shared) {
            $reasons[] = __('You both like :things', ['things' => implode(', ', array_slice($shared, 0, 3))]);
        }

        return $reasons;
    }

    private static function familyPlans(?string $mine, ?string $theirs): int
    {
        if (! $mine || ! $theirs) {
            return 0;
        }

        if ($mine === $theirs) {
            return 10;
        }

        $clash = [$mine, $theirs] === ['want', 'dont_want'] || [$mine, $theirs] === ['dont_want', 'want'];

        return $clash ? -15 : 3;
    }
}
