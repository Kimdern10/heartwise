<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * "We met" feedback: did a match lead to a real date, and would they see them again.
 *
 * @property int $match_id
 * @property int $user_id
 * @property bool $met
 * @property int|null $rating
 */
#[Fillable(['match_id', 'user_id', 'met', 'rating'])]
class DateFeedback extends Model
{
    protected $table = 'date_feedback';

    protected function casts(): array
    {
        return ['met' => 'boolean'];
    }
}
