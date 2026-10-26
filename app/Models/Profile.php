<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\ModerationStatus;
use App\Enums\RelationshipGoal;
use App\Enums\Seeking;
use App\Support\Geo;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $display_name
 * @property Gender $gender
 * @property Seeking $seeking
 * @property RelationshipGoal|null $relationship_goal
 * @property string|null $bio
 * @property string $city
 * @property string $state
 * @property int|null $height_cm
 * @property ModerationStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $photo_verified_at
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon|null $location_verified_at
 * @property bool $is_visible
 * @property bool $show_last_active
 * @property bool $browse_privately
 * @property string|null $job_title
 * @property string|null $school
 * @property string|null $education
 * @property string|null $religion
 * @property string|null $politics
 * @property string|null $has_kids
 * @property string|null $wants_kids
 * @property string|null $drinking
 * @property string|null $smoking
 * @property list<string>|null $interests
 * @property int|null $pref_age_min
 * @property int|null $pref_age_max
 * @property int|null $pref_max_miles
 * @property list<string>|null $dealbreakers
 * @property array<string, list<string>>|null $pref_values
 * @property-read User $user
 */
#[Fillable([
    'display_name', 'gender', 'seeking', 'relationship_goal', 'bio', 'city', 'state',
    'height_cm', 'is_visible', 'show_last_active', 'browse_privately',
    'job_title', 'school', 'education', 'religion', 'politics', 'has_kids', 'wants_kids', 'drinking', 'smoking',
    'interests', 'pref_age_min', 'pref_age_max', 'pref_max_miles', 'dealbreakers', 'pref_values',
])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'is_visible' => true,
        'show_last_active' => true,
        'browse_privately' => false,
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'seeking' => Seeking::class,
            'relationship_goal' => RelationshipGoal::class,
            'status' => ModerationStatus::class,
            'photo_verified_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_verified_at' => 'datetime',
            'is_visible' => 'boolean',
            'show_last_active' => 'boolean',
            'browse_privately' => 'boolean',
            'interests' => 'array',
            'dealbreakers' => 'array',
            'pref_values' => 'array',
        ];
    }

    /**
     * Filled-in lifestyle details as [key => [label, value label, icon]].
     *
     * @return array<string, array{label: string, value: string, icon: string}>
     */
    public function vitals(): array
    {
        $vitals = [];

        foreach (config('profile_options.vitals') as $key => $vital) {
            if ($this->{$key} && isset($vital['options'][$this->{$key}])) {
                $vitals[$key] = ['label' => $vital['label'], 'value' => $vital['options'][$this->{$key}], 'icon' => $vital['icon']];
            }
        }

        return $vitals;
    }

    public function hasDealbreaker(string $key): bool
    {
        return in_array($key, $this->dealbreakers ?? [], true);
    }

    /**
     * Preferred values for a vital (e.g. religions this member is open to).
     *
     * @return list<string>
     */
    public function preferred(string $key): array
    {
        return array_values($this->pref_values[$key] ?? []);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): string
    {
        return $this->city.', '.$this->state;
    }

    public function isLocationVerified(): bool
    {
        return $this->location_verified_at !== null;
    }

    /**
     * Approximate distance in miles, when both members verified their location.
     */
    public function milesFrom(?Profile $other): ?int
    {
        if (! $other || $this->latitude === null || $other->latitude === null) {
            return null;
        }

        return (int) max(1, round(Geo::distanceMiles($this->latitude, $this->longitude, $other->latitude, $other->longitude)));
    }

    /**
     * Save the device location (rounded to ~1 km) if it is inside the profile's state.
     */
    public function verifyLocation(float $lat, float $lng): bool
    {
        if (! Geo::isInState($lat, $lng, $this->state)) {
            return false;
        }

        $this->forceFill([
            'latitude' => round($lat, 2),
            'longitude' => round($lng, 2),
            'location_verified_at' => now(),
        ])->save();

        return true;
    }

    public function heightLabel(): ?string
    {
        if ($this->height_cm === null) {
            return null;
        }

        $inches = (int) round($this->height_cm / 2.54);

        return intdiv($inches, 12).'′ '.($inches % 12).'″';
    }
}
