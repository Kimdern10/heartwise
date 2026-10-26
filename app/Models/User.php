<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\ModerationStatus;
use App\Notifications\EmailVerificationCode;
use Database\Factories\UserFactory;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $terms_accepted_at
 * @property Carbon|null $onboarded_at
 * @property string|null $flag_reason
 * @property bool $is_admin
 * @property AccountStatus $status
 * @property Carbon|null $suspended_until
 * @property string|null $status_reason
 * @property Carbon|null $last_active_at
 * @property bool $is_sample
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Profile|null $profile
 */
#[Fillable(['name', 'email', 'password', 'date_of_birth', 'terms_accepted_at', 'onboarded_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'email_code_hash'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'is_admin' => false,
        'is_sample' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_code_expires_at' => 'datetime',
            'email_code_attempts' => 'integer',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'terms_accepted_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'is_admin' => 'boolean',
            'status' => AccountStatus::class,
            'suspended_until' => 'datetime',
            'last_active_at' => 'datetime',
            'is_sample' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Delete photo and selfie files too; the database rows cascade on their own.
        static::deleting(function (User $user) {
            $user->photos()->get()->each->delete();
            $user->verificationRequests()->get()->each->delete();
        });
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->displayName(), true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /** @return HasOne<Profile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Photo, $this> */
    public function approvedPhotos(): HasMany
    {
        return $this->photos()->where('status', ModerationStatus::Approved);
    }

    /** @return HasMany<ProfilePrompt, $this> */
    public function prompts(): HasMany
    {
        return $this->hasMany(ProfilePrompt::class)->orderBy('position');
    }

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function hasFavorited(User $other): bool
    {
        return $this->favorites()->where('favorite_id', $other->id)->exists();
    }

    /**
     * Sparks (stand-out likes): 1 a day free, more with Premium.
     */
    public function hasSparkLeftToday(): bool
    {
        return Like::query()->where('user_id', $this->id)->where('is_spark', true)
            ->where('updated_at', '>=', now()->startOfDay())->count() < $this->sparksPerDay();
    }

    /** @return HasMany<Like, $this> */
    public function likesGiven(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /** @return HasMany<Block, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    /** @return HasMany<VerificationRequest, $this> */
    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class)->latest('id');
    }

    /**
     * We verify emails with a 6-digit code instead of a link.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new EmailVerificationCode($this->issueEmailCode()));
    }

    public function issueEmailCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'email_code_hash' => Hash::make($code),
            'email_code_expires_at' => now()->addMinutes(15),
            'email_code_attempts' => 0,
        ])->save();

        // Local development has no real mail server, so keep the code where the
        // verify screen can show it. Never happens on the live site.
        if (app()->isLocal() && in_array(config('mail.default'), ['log', 'array'], true)) {
            cache()->put('dev-email-code:'.$this->id, $code, now()->addMinutes(15));
        }

        return $code;
    }

    /**
     * Check a code and verify the email. Five wrong tries cancel the code.
     */
    public function verifyEmailCode(string $code): bool
    {
        if (! $this->email_code_hash || $this->email_code_expires_at?->isPast() || $this->email_code_attempts >= 5) {
            return false;
        }

        if (! Hash::check(preg_replace('/\D/', '', $code), $this->email_code_hash)) {
            $this->increment('email_code_attempts');

            return false;
        }

        $this->forceFill(['email_code_hash' => null, 'email_code_expires_at' => null, 'email_code_attempts' => 0]);
        $this->markEmailAsVerified();

        event(new Verified($this));

        return true;
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    /**
     * Premium members have an active (or trialing / cancelled-but-paid-up) subscription.
     */
    public function isPremium(): bool
    {
        return $this->is_admin || $this->subscribed('premium');
    }

    public function likesPerDay(): int
    {
        return config($this->isPremium() ? 'premium.premium.likes_per_day' : 'premium.free.likes_per_day');
    }

    public function likesLeftToday(): int
    {
        $used = Like::query()->where('user_id', $this->id)->where('liked', true)
            ->where('updated_at', '>=', now()->startOfDay())->count();

        return max(0, $this->likesPerDay() - $used);
    }

    public function sparksPerDay(): int
    {
        return config($this->isPremium() ? 'premium.premium.sparks_per_day' : 'premium.free.sparks_per_day');
    }

    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasMany<ConnectionCheck, $this> */
    public function connectionChecks(): HasMany
    {
        return $this->hasMany(ConnectionCheck::class)->latest('id');
    }

    public function displayName(): string
    {
        return $this->profile?->display_name ?: Str::before($this->name, ' ');
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function isPhotoVerified(): bool
    {
        return $this->profile?->photo_verified_at !== null;
    }

    public function primaryPhoto(): ?Photo
    {
        return $this->approvedPhotos->first();
    }

    /**
     * IDs of everyone this user blocked or was blocked by.
     *
     * @return list<int>
     */
    public function blockedUserIds(): array
    {
        return Block::query()
            ->where('user_id', $this->id)->pluck('blocked_id')
            ->merge(Block::query()->where('blocked_id', $this->id)->pluck('user_id'))
            ->unique()->values()->all();
    }

    public function hasBlockBetween(User $other): bool
    {
        return Block::query()
            ->where(fn (Builder $q) => $q->where('user_id', $this->id)->where('blocked_id', $other->id))
            ->orWhere(fn (Builder $q) => $q->where('user_id', $other->id)->where('blocked_id', $this->id))
            ->exists();
    }

    public function unreadMessagesCount(): int
    {
        return Message::query()
            ->whereIn('match_id', UserMatch::query()->involving($this)->active()->select('id'))
            ->where('sender_id', '!=', $this->id)
            ->whereNotIn('sender_id', $this->blockedUserIds())
            ->whereNull('read_at')
            ->count();
    }

    public function matchWith(User $other): ?UserMatch
    {
        return UserMatch::between($this, $other)->active()->first();
    }

    /**
     * Members other people may see: active, verified email, approved and visible
     * profile, and at least one approved photo.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function discoverable(Builder $query): void
    {
        $query->where('status', AccountStatus::Active)
            ->whereNotNull('email_verified_at')
            ->whereHas('profile', fn (Builder $q) => $q
                ->where('status', ModerationStatus::Approved)
                ->where('is_visible', true))
            ->whereHas('photos', fn (Builder $q) => $q->where('status', ModerationStatus::Approved));
    }
}
