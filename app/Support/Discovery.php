<?php

namespace App\Support;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the "people you might like" query for a member: discoverable,
 * mutually compatible by gender preference, not blocked either way, and
 * respecting the member's dealbreakers. Search filters narrow it further.
 */
class Discovery
{
    /**
     * @param  array<string, mixed>  $filters
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

            self::applyDealbreakers($query, $profile);
        }

        $ageMin = max((int) ($filters['age_min'] ?? config('dating.minimum_age')), config('dating.minimum_age'));
        $ageMax = min((int) ($filters['age_max'] ?? config('dating.maximum_age')), config('dating.maximum_age'));

        $query->where('date_of_birth', '<=', now()->subYears($ageMin)->toDateString())
            ->where('date_of_birth', '>', now()->subYears($ageMax + 1)->toDateString());

        $profileFilters = array_filter([
            'state' => $filters['state'] ?? null,
            'relationship_goal' => $filters['goal'] ?? null,
            'religion' => $filters['religion'] ?? null,
            'education' => $filters['education'] ?? null,
            'has_kids' => $filters['has_kids'] ?? null,
            'wants_kids' => $filters['wants_kids'] ?? null,
            'drinking' => $filters['drinking'] ?? null,
            'smoking' => $filters['smoking'] ?? null,
        ]);

        if ($profileFilters) {
            $query->whereHas('profile', function (Builder $q) use ($profileFilters) {
                foreach ($profileFilters as $column => $value) {
                    $q->where($column, $value);
                }
            });
        }

        if (! empty($filters['height_min']) || ! empty($filters['height_max'])) {
            $query->whereHas('profile', fn (Builder $q) => $q
                ->when($filters['height_min'] ?? null, fn ($q, $in) => $q->where('height_cm', '>=', (int) floor($in * 2.54)))
                ->when($filters['height_max'] ?? null, fn ($q, $in) => $q->where('height_cm', '<=', (int) ceil($in * 2.54))));
        }

        // "Near me": within N miles of the viewer's verified location.
        if (! empty($filters['within_miles']) && $profile?->latitude !== null) {
            self::withinMiles($query, $profile->latitude, $profile->longitude, (int) $filters['within_miles']);
        }

        if (! empty($filters['verified'])) {
            $query->whereHas('profile', fn (Builder $q) => $q->whereNotNull('photo_verified_at'));
        }

        if (! empty($filters['online'])) {
            $query->where('last_active_at', '>=', now()->subMinutes(15))
                ->whereHas('profile', fn (Builder $q) => $q->where('show_last_active', true));
        }

        if (! empty($filters['has_prompts'])) {
            $query->whereHas('prompts');
        }

        if ($filters['hide_seen'] ?? true) {
            $query->whereNotIn('users.id', Like::query()->where('user_id', $viewer->id)->select('target_id'));
        }

        return $query->orderByDesc('last_active_at')->orderByDesc('users.id');
    }

    /**
     * Today's single "Most Compatible" pick plus a few Standouts, cached per day.
     *
     * @return array{pick: User|null, standouts: Collection<int, User>}
     */
    public static function dailyPicks(User $viewer): array
    {
        $profile = $viewer->profile;

        if (! $profile) {
            return ['pick' => null, 'standouts' => collect()];
        }

        $ids = Cache::remember('daily-picks:'.$viewer->id.':'.now()->toDateString(), now()->endOfDay(), function () use ($viewer, $profile) {
            // Prefer people in the member's own state; fall back to the whole US.
            $candidates = self::for($viewer, ['state' => $profile->state])->with('profile.user')->limit(60)->get();

            if ($candidates->count() < 3) {
                $candidates = self::for($viewer)->with('profile.user')->limit(60)->get();
            }

            return $candidates
                ->sortByDesc(fn (User $u) => Compatibility::score($profile, $u->profile))
                ->take(7)
                ->pluck('id')
                ->all();
        });

        // Drop anyone the member has since liked, passed or blocked.
        $people = self::for($viewer)->whereIn('users.id', $ids)->with('prompts')->get()
            ->sortBy(fn (User $u) => array_search($u->id, $ids))->values();

        return ['pick' => $people->first(), 'standouts' => $people->slice(1)->values()];
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyDealbreakers(Builder $query, $profile): void
    {
        if ($profile->hasDealbreaker('age') && $profile->pref_age_min && $profile->pref_age_max) {
            $query->where('date_of_birth', '<=', now()->subYears($profile->pref_age_min)->toDateString())
                ->where('date_of_birth', '>', now()->subYears($profile->pref_age_max + 1)->toDateString());
        }

        foreach (['religion', 'has_kids', 'wants_kids', 'drinking', 'smoking'] as $key) {
            if ($profile->hasDealbreaker($key) && $profile->preferred($key)) {
                $query->whereHas('profile', fn (Builder $q) => $q->whereIn($key, $profile->preferred($key)));
            }
        }

        if ($profile->hasDealbreaker('verified')) {
            $query->whereHas('profile', fn (Builder $q) => $q->whereNotNull('photo_verified_at'));
        }

        if ($profile->hasDealbreaker('distance') && $profile->pref_max_miles && $profile->latitude !== null) {
            self::withinMiles($query, $profile->latitude, $profile->longitude, $profile->pref_max_miles);
        }
    }

    /**
     * Distance needs both people to have verified their location; uses a
     * simple bounding box (1° latitude ≈ 69 miles) so it works on any database.
     *
     * @param  Builder<User>  $query
     */
    private static function withinMiles(Builder $query, float $lat, float $lng, int $miles): void
    {
        $latDelta = $miles / 69;
        $lngDelta = $miles / max(1, 69 * cos(deg2rad($lat)));

        $query->whereHas('profile', fn (Builder $q) => $q
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]));
    }
}
