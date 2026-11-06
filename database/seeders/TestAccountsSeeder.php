<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\ModerationStatus;
use App\Enums\RelationshipGoal;
use App\Enums\Seeking;
use App\Models\Like;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserMatch;
use App\Support\MessageScreener;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * LOCAL TESTING ONLY — 14 clearly-marked test accounts (is_sample = true) to
 * try matching and chatting from start to finish. Never run this on the live site.
 *
 *   php artisan db:seed --class=TestAccountsSeeder     (create / reset)
 *   php artisan app:remove-test-accounts               (delete them all)
 *
 * Log in as test1@heartwise.test … test26@heartwise.test, password: password
 *   test1–test7  = men looking for women   (test1 is a FREE member, test2 is PREMIUM)
 *   test8–test14 = women looking for men
 *
 * Ready-made situations for test1:
 *   - a match with test8 that already has a chat (5 days old → shows "Have you met?")
 *   - a match with test9 with no messages yet ("Say hello")
 *   - a match with test10 whose message mentions WhatsApp (shows the safety warning)
 *   - likes waiting from test11 (comment on a prompt), test12 (Spark) and test13 (photo)
 *   - test14 has not liked anyone: like her as test1, then log in as test14 and like back
 * test2 (Premium) has likes from test11–test14 so you can compare free vs Premium.
 *
 * NEW YORK: log in as test15@heartwise.test (Andre, Premium, New York, NY):
 *   - a match with test16 that already has a chat (her last message is unread)
 *   - a match with test17 with no messages yet
 *   - likes waiting from test18 (comment), test19 (Spark) and test20 (photo): like back to match
 *   - test21–test24 (New York) and test25 (Yonkers) haven't liked anyone yet: like them as
 *     test15, then log in as them and like back
 *   - test26 (Noah) is another man in New York, so the women have someone to see too
 */
class TestAccountsSeeder extends Seeder
{
    private const PEOPLE = [
        1 => ['Marcus', 'man', 31, RelationshipGoal::LongTerm, 'Software developer', [30.27, -97.74], 'Austin'],
        2 => ['Daniel', 'man', 34, RelationshipGoal::Marriage, 'Nurse', [30.31, -97.70], 'Austin'],
        3 => ['Jamal', 'man', 28, RelationshipGoal::Dating, 'Chef', [30.40, -97.72], 'Austin'],
        4 => ['Ethan', 'man', 36, RelationshipGoal::LongTerm, 'Accountant', [30.51, -97.68], 'Round Rock'],
        5 => ['Luis', 'man', 29, RelationshipGoal::NotSure, 'Teacher', [30.63, -97.68], 'Georgetown'],
        6 => ['Ryan', 'man', 33, RelationshipGoal::LongTerm, 'Firefighter', [29.88, -97.94], 'San Marcos'],
        7 => ['Tyler', 'man', 26, RelationshipGoal::Dating, 'Designer', [30.20, -97.77], 'Austin'],
        8 => ['Sophia', 'woman', 30, RelationshipGoal::LongTerm, 'Pharmacist', [30.29, -97.74], 'Austin'],
        9 => ['Ava', 'woman', 27, RelationshipGoal::Dating, 'Photographer', [30.25, -97.75], 'Austin'],
        10 => ['Mia', 'woman', 32, RelationshipGoal::LongTerm, 'Marketing manager', [30.35, -97.71], 'Austin'],
        11 => ['Olivia', 'woman', 29, RelationshipGoal::Marriage, 'Teacher', [30.50, -97.66], 'Round Rock'],
        12 => ['Emma', 'woman', 35, RelationshipGoal::LongTerm, 'Lawyer', [30.22, -97.80], 'Austin'],
        13 => ['Chloe', 'woman', 25, RelationshipGoal::Dating, 'Barista', [30.45, -97.62], 'Pflugerville'],
        14 => ['Grace', 'woman', 31, RelationshipGoal::LongTerm, 'Physical therapist', [30.28, -97.73], 'Austin'],

        // New York, NY (test15 is the login for trying matching there)
        15 => ['Andre', 'man', 32, RelationshipGoal::LongTerm, 'Architect', [40.7484, -73.9857], 'New York', 'NY'],
        16 => ['Isabella', 'woman', 29, RelationshipGoal::LongTerm, 'Product designer', [40.7306, -73.9866], 'New York', 'NY'],
        17 => ['Zoe', 'woman', 27, RelationshipGoal::Dating, 'Journalist', [40.7831, -73.9712], 'New York', 'NY'],
        18 => ['Nia', 'woman', 31, RelationshipGoal::Marriage, 'Doctor', [40.8116, -73.9465], 'New York', 'NY'],
        19 => ['Hannah', 'woman', 30, RelationshipGoal::LongTerm, 'Teacher', [40.7580, -73.9855], 'New York', 'NY'],
        20 => ['Camila', 'woman', 28, RelationshipGoal::Dating, 'Dancer', [40.7193, -74.0007], 'New York', 'NY'],
        21 => ['Leah', 'woman', 33, RelationshipGoal::LongTerm, 'Financial analyst', [40.7069, -74.0113], 'New York', 'NY'],
        22 => ['Priya', 'woman', 29, RelationshipGoal::NotSure, 'Software engineer', [40.7411, -73.9897], 'New York', 'NY'],
        23 => ['Maya', 'woman', 26, RelationshipGoal::Dating, 'Bartender', [40.7265, -73.9815], 'New York', 'NY'],
        24 => ['Julia', 'woman', 34, RelationshipGoal::Marriage, 'Lawyer', [40.7736, -73.9566], 'New York', 'NY'],
        25 => ['Aaliyah', 'woman', 30, RelationshipGoal::LongTerm, 'Nurse', [40.9312, -73.8988], 'Yonkers', 'NY'],
        26 => ['Noah', 'man', 30, RelationshipGoal::LongTerm, 'Photographer', [40.7128, -74.0060], 'New York', 'NY'],
    ];

    private const PROMPTS = [
        ['My ideal Sunday looks like', 'Breakfast tacos, a long walk around Lady Bird Lake, and a nap I don’t apologise for.'],
        ['The way to win me over is', 'Recommend me a song I’ve never heard and actually love.'],
        ['A perfect first date would be', 'Coffee that turns into dinner because neither of us wants to leave.'],
        ['My simple pleasures', 'Fresh sheets, live music on a Tuesday, and a good thunderstorm.'],
        ['I’m looking for someone who', 'Laughs easily, texts back, and wants to build something real.'],
        ['My most unpopular food opinion', 'Brisket is overrated. Fight me (politely).'],
        ['We’ll get along if', 'You’re kind to waiters and up for a spontaneous road trip.'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Test accounts must never be created on the live site.');
        }

        static::removeAll();

        $users = [];
        foreach (self::PEOPLE as $n => $person) {
            $users[$n] = $this->createPerson($n, ...$person);
        }

        $this->subscribePremium($users[2]);
        $this->subscribePremium($users[15]);
        $this->buildScenarios($users);
        $this->buildNewYorkScenarios($users);
    }

    /**
     * Deletes every test account (and their photos, likes, matches and messages).
     */
    public static function removeAll(): int
    {
        $users = User::query()->where('is_sample', true)->where('email', 'like', 'test%@heartwise.test')->get();
        $users->each->delete();

        return $users->count();
    }

    private function createPerson(int $n, string $name, string $gender, int $age, RelationshipGoal $goal, string $job, array $coords, string $city, string $state = 'TX'): User
    {
        $isMan = $gender === 'man';

        $user = User::factory()->create([
            'name' => $name.' Test',
            'email' => "test{$n}@heartwise.test",
            'date_of_birth' => now()->subYears($age)->subDays($n * 11)->toDateString(),
            'is_sample' => true,
            'last_active_at' => now()->subMinutes($n * 7),
        ]);

        $profile = new Profile([
            'display_name' => $name,
            'gender' => $isMan ? Gender::Man : Gender::Woman,
            'seeking' => $isMan ? Seeking::Women : Seeking::Men,
            'relationship_goal' => $goal,
            'bio' => "Test account #{$n} for trying out {$this->app()}. Not a real person.",
            'city' => $city,
            'state' => $state,
            'height_cm' => $isMan ? 175 + $n : 160 + ($n % 5) * 2,
            'job_title' => $job,
            'education' => ['bachelors', 'masters', 'some_college', 'associates'][$n % 4],
            'religion' => ['christian', 'catholic', 'spiritual', 'agnostic'][$n % 4],
            'drinking' => ['sometimes', 'no', 'yes'][$n % 3],
            'smoking' => 'no',
            'wants_kids' => ['want', 'open', 'not_sure'][$n % 3],
            'has_kids' => 'no',
            'interests' => array_slice(config('profile_options.interests'), ($n * 3) % 28, 5),
        ]);
        $profile->user_id = $user->id;
        $profile->forceFill([
            'status' => ModerationStatus::Approved,
            'latitude' => $coords[0],
            'longitude' => $coords[1],
            'location_verified_at' => now(),
            'photo_verified_at' => $n % 3 === 0 ? now() : null,
        ])->save();

        foreach ([0, 1, 2] as $i) {
            [$prompt, $answer] = self::PROMPTS[($n + $i) % count(self::PROMPTS)];
            $user->prompts()->create(['prompt' => $prompt, 'answer' => $answer, 'position' => $i]);
        }

        foreach ([0, 1] as $i) {
            $photo = new Photo([...$this->placeholderPhoto($user->id, $name, $n + $i * 7), 'position' => $i]);
            $photo->user_id = $user->id;
            $photo->status = ModerationStatus::Approved;
            $photo->save();
        }

        return $user->fresh(['profile', 'prompts', 'approvedPhotos']);
    }

    private function subscribePremium(User $user): void
    {
        // A local-only stand-in subscription so Premium perks switch on without Stripe.
        $user->subscriptions()->create([
            'type' => 'premium',
            'stripe_id' => 'sub_test_local_'.$user->id,
            'stripe_status' => 'active',
            'stripe_price' => 'price_test_local',
            'quantity' => 1,
        ]);
    }

    /**
     * @param  array<int, User>  $u
     */
    private function buildScenarios(array $u): void
    {
        // Match 1↔8 with a chat that has been going for 5 days.
        $this->mutual($u[1], $u[8]);
        $chat = UserMatch::between($u[1], $u[8])->first();
        $chat->forceFill(['created_at' => now()->subDays(5)])->save();
        $lines = [
            [8, 'Hey Marcus! Your Sunday prompt sounds exactly like mine 😄', 5 * 24 * 60],
            [1, 'Ha! Lady Bird Lake is the best. Do you run there or just walk?', 5 * 24 * 60 - 30],
            [8, 'Walk, always. Running is for people who like suffering.', 4 * 24 * 60],
            [1, 'Fair. Coffee this weekend? I know a great place on South Congress.', 3 * 24 * 60],
            [8, 'I’d love that. Saturday at 11?', 2 * 24 * 60],
            [1, 'Perfect, see you then!', 2 * 24 * 60 - 10],
        ];
        foreach ($lines as [$from, $text, $minutesAgo]) {
            $msg = $chat->messages()->create(['sender_id' => $u[$from]->id, 'body' => $text]);
            $msg->forceFill(['created_at' => now()->subMinutes($minutesAgo), 'read_at' => now()])->save();
        }
        $chat->forceFill(['last_message_at' => now()->subMinutes(2 * 24 * 60 - 10)])->save();

        // Match 1↔9, nobody has said hello yet.
        $this->mutual($u[1], $u[9]);

        // Match 1↔10 where Mia's message trips the safety warning.
        $this->mutual($u[1], $u[10]);
        $warn = UserMatch::between($u[1], $u[10])->first();
        $text = 'Hi! I’m not on here much, can we talk on WhatsApp instead?';
        $warn->messages()->create(['sender_id' => $u[10]->id, 'body' => $text, 'flag' => (new MessageScreener)->flag($text)]);
        $warn->forceFill(['last_message_at' => now()->subHours(3)])->save();

        // Likes waiting for test1 (free member: sees one, the rest locked).
        Like::create(['user_id' => $u[11]->id, 'target_id' => $u[1]->id, 'liked' => true,
            'prompt_id' => $u[1]->prompts->first()->id, 'comment' => 'Okay but which song would you pick for me? 🎶']);
        Like::create(['user_id' => $u[12]->id, 'target_id' => $u[1]->id, 'liked' => true, 'is_spark' => true,
            'comment' => 'Your profile made me smile. Spark sent ✨']);
        Like::create(['user_id' => $u[13]->id, 'target_id' => $u[1]->id, 'liked' => true,
            'photo_id' => $u[1]->approvedPhotos->first()->id]);

        // Likes waiting for test2 (Premium member: sees everyone).
        foreach ([11, 12, 13, 14] as $n) {
            Like::create(['user_id' => $u[$n]->id, 'target_id' => $u[2]->id, 'liked' => true,
                'comment' => $n === 14 ? 'Fellow coffee snob? 👀' : null]);
        }
    }

    /**
     * New York: log in as test15 (Andre, Premium, so likes are unlimited).
     *
     * @param  array<int, User>  $u
     */
    private function buildNewYorkScenarios(array $u): void
    {
        // A match that already has a short chat (her last message is unread).
        $this->mutual($u[15], $u[16]);
        $chat = UserMatch::between($u[15], $u[16])->first();
        $lines = [
            [16, 'Hi Andre! An architect — what’s your favourite building in the city?', 90],
            [15, 'The Flatiron, every time. Yours?', 75],
            [16, 'The library on 5th. Coffee near Bryant Park sometime?', 20],
        ];
        foreach ($lines as [$from, $text, $minutesAgo]) {
            $msg = $chat->messages()->create(['sender_id' => $u[$from]->id, 'body' => $text]);
            $msg->forceFill(['created_at' => now()->subMinutes($minutesAgo), 'read_at' => $minutesAgo > 30 ? now() : null])->save();
        }
        $chat->forceFill(['last_message_at' => now()->subMinutes(20)])->save();

        // A fresh match, nobody has said hello yet.
        $this->mutual($u[15], $u[17]);

        // People who already like Andre: like them back to match instantly.
        Like::create(['user_id' => $u[18]->id, 'target_id' => $u[15]->id, 'liked' => true,
            'prompt_id' => $u[15]->prompts->first()->id, 'comment' => 'Okay, I need the story behind this answer 😄']);
        Like::create(['user_id' => $u[19]->id, 'target_id' => $u[15]->id, 'liked' => true, 'is_spark' => true,
            'comment' => 'Spark! You seem lovely ✨']);
        Like::create(['user_id' => $u[20]->id, 'target_id' => $u[15]->id, 'liked' => true,
            'photo_id' => $u[15]->approvedPhotos->first()->id]);

        // test21–test24 (New York) and test25 (Yonkers) haven't liked anyone yet.
    }

    private function mutual(User $a, User $b): void
    {
        Like::create(['user_id' => $a->id, 'target_id' => $b->id, 'liked' => true]);
        Like::create(['user_id' => $b->id, 'target_id' => $a->id, 'liked' => true]);
        UserMatch::createBetween($a, $b);
    }

    /**
     * A soft gradient with the name and "TEST" — clearly not a real person.
     *
     * @return array{path: string, path_sm: string}
     */
    private function placeholderPhoto(int $userId, string $name, int $seed): array
    {
        $palettes = [[244, 63, 94], [14, 165, 233], [234, 179, 8], [34, 197, 94], [168, 85, 247], [249, 115, 22], [20, 184, 166]];
        $rgb = $palettes[$seed % count($palettes)];
        $paths = [];

        foreach (['path' => [900, 1125], 'path_sm' => [400, 500]] as $key => [$w, $h]) {
            $image = imagecreatetruecolor($w, $h);

            for ($y = 0; $y < $h; $y++) {
                $t = $y / $h;
                $color = imagecolorallocate($image,
                    (int) ($rgb[0] + (30 - $rgb[0]) * $t * 0.8),
                    (int) ($rgb[1] + (20 - $rgb[1]) * $t * 0.8),
                    (int) ($rgb[2] + (60 - $rgb[2]) * $t * 0.8));
                imageline($image, 0, $y, $w, $y, $color);
            }

            $white = imagecolorallocate($image, 255, 255, 255);
            $label = strtoupper($name).' - TEST';
            $font = 5;
            imagestring($image, $font, (int) (($w - imagefontwidth($font) * strlen($label)) / 2), (int) ($h / 2) - 8, $label, $white);

            ob_start();
            imagewebp($image, null, 82);
            $path = "photos/{$userId}/test-".Str::random(10).($key === 'path_sm' ? '-sm' : '').'.webp';
            Storage::disk('local')->put($path, (string) ob_get_clean());
            imagedestroy($image);

            $paths[$key] = $path;
        }

        return $paths;
    }

    private function app(): string
    {
        return config('app.name');
    }
}
