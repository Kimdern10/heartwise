<?php

namespace App\Support;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the "people you might like" query for a member: discoverable,
 * mutually compatible by gender preference, and not blocked either way.
 */
class Discovery
{
    /**
     * @param  array{age_min?: int|null, age_max?: int|null, state?: string|null, verified?: bool, goal?: string|null, hide_seen?: bool}  $filters
     * @return Builder<User>
     */
    public static function for(User $viewer, array $filters = []): Builder
    {
        $profile = $viewer->profile;

        $query = User::query()
            ->discoverable()
            ->whereKeyNot($viewer->id)
            ->whereNotIn('users.id', $viewer->blockedUserIds())
            ->with(['profile', 'approvedPhotos']);

        if ($profile) {
            $query->whereHas('profile', fn (Builder $q) => $q
                ->whereIn('gender', $profile->seeking->genders())
                ->whereIn('seeking', $profile->gender->seekingValuesThatInclude()));
        }

        $ageMin = max((int) ($filters['age_min'] ?? config('dating.minimum_age')), config('dating.minimum_age'));
        $ageMax = min((int) ($filters['age_max'] ?? config('dating.maximum_age')), config('dating.maximum_age'));

        $query->where('date_of_birth', '<=', now()->subYears($ageMin)->toDateString())
            ->where('date_of_birth', '>', now()->subYears($ageMax + 1)->toDateString());

        if (! empty($filters['state'])) {
            $query->whereHas('profile', fn (Builder $q) => $q->where('state', $filters['state']));
        }

        if (! empty($filters['goal'])) {
            $query->whereHas('profile', fn (Builder $q) => $q->where('relationship_goal', $filters['goal']));
        }

        if (! empty($filters['verified'])) {
            $query->whereHas('profile', fn (Builder $q) => $q->whereNotNull('photo_verified_at'));
        }

        if ($filters['hide_seen'] ?? true) {
            $query->whereNotIn('users.id', Like::query()->where('user_id', $viewer->id)->select('target_id'));
        }

        return $query->orderByDesc('last_active_at')->orderByDesc('users.id');
    }
}
