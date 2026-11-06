<?php

namespace App\Support;

use App\Models\User;

/**
 * Suggested first messages based on the other person's profile: their
 * prompt answers, shared interests and where they live.
 */
class Icebreakers
{
    /**
     * @return list<string>
     */
    public static function for(User $me, User $them): array
    {
        $ideas = [];
        $templates = [
            'Okay, I need the story behind your answer to “:prompt” 😄',
            'Your answer to “:prompt” made me smile. What else should I know about you?',
        ];

        foreach ($them->prompts()->limit(2)->get() as $i => $prompt) {
            $ideas[] = __($templates[$i % 2], ['prompt' => $prompt->prompt]);
        }

        $shared = array_values(array_intersect($me->profile?->interests ?? [], $them->profile?->interests ?? []));
        if ($shared) {
            $ideas[] = __('I see we’re both into :thing — what got you into it?', ['thing' => strtolower($shared[0])]);
        } elseif ($them->profile?->city) {
            $ideas[] = __('What’s your favorite spot in :city? I’m always looking for new places.', ['city' => $them->profile->city]);
        }

        return array_slice($ideas, 0, 3);
    }
}
