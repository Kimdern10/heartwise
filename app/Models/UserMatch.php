<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A mutual like. user_one_id is always the lower user ID.
 *
 * @property int $id
 * @property int $user_one_id
 * @property int $user_two_id
 * @property Carbon|null $last_message_at
 * @property Carbon $created_at
 * @property-read User $userOne
 * @property-read User $userTwo
 */
#[Fillable(['user_one_id', 'user_two_id', 'last_message_at'])]
class UserMatch extends Model
{
    protected $table = 'matches';

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public static function createBetween(User $a, User $b): self
    {
        return static::firstOrCreate([
            'user_one_id' => min($a->id, $b->id),
            'user_two_id' => max($a->id, $b->id),
        ]);
    }

    /**
     * @param  Builder<UserMatch>  $query
     */
    #[Scope]
    protected function between(Builder $query, User $a, User $b): void
    {
        $query->where('user_one_id', min($a->id, $b->id))
            ->where('user_two_id', max($a->id, $b->id));
    }

    /**
     * @param  Builder<UserMatch>  $query
     */
    #[Scope]
    protected function involving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id));
    }

    /** @return BelongsTo<User, $this> */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /** @return BelongsTo<User, $this> */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'match_id');
    }

    /** @return HasOne<Message, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'match_id')->latestOfMany();
    }

    public function includes(User $user): bool
    {
        return in_array($user->id, [$this->user_one_id, $this->user_two_id], true);
    }

    public function otherUser(User $user): User
    {
        return $this->user_one_id === $user->id ? $this->userTwo : $this->userOne;
    }
}
