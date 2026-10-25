<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\ModerationStatus;
use App\Enums\Seeking;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * LOCAL TESTING ONLY. Creates clearly-labelled sample members (is_sample = true,
 * emails ending in example.test, "SAMPLE" placeholder pictures — no real faces)
 * so the app can be clicked through. Never run this on the live site.
 */
class SampleMembersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample members must never be created on the live site.');
        }

        $colors = [[244, 63, 94], [14, 165, 233], [234, 179, 8], [34, 197, 94], [168, 85, 247], [249, 115, 22]];

        foreach (range(1, 16) as $i) {
            $gender = $i % 2 ? Gender::Woman : Gender::Man;

            $user = User::factory()->create([
                'name' => 'Sample Member '.$i,
                'email' => "sample{$i}@example.test",
                'is_sample' => true,
                'last_active_at' => now()->subMinutes($i * 37),
            ]);

            Profile::factory()->for($user)->approved()->create([
                'display_name' => 'Sample '.$i,
                'gender' => $gender,
                'seeking' => $gender === Gender::Woman ? Seeking::Men : Seeking::Women,
                'bio' => 'This is a sample profile for local testing only. It is not a real person.',
                'photo_verified_at' => $i % 3 === 0 ? now() : null,
            ]);

            $paths = $this->placeholder($user->id, 'SAMPLE '.$i, $colors[$i % count($colors)]);

            $photo = new Photo([...$paths, 'position' => 0]);
            $photo->user_id = $user->id;
            $photo->status = ModerationStatus::Approved;
            $photo->save();
        }
    }

    /**
     * @param  array{int, int, int}  $rgb
     * @return array{path: string, path_sm: string}
     */
    private function placeholder(int $userId, string $label, array $rgb): array
    {
        $paths = [];

        foreach (['path' => [900, 1125], 'path_sm' => [400, 500]] as $key => [$w, $h]) {
            $image = imagecreatetruecolor($w, $h);

            // Diagonal-ish vertical gradient from the colour to a soft plum.
            for ($y = 0; $y < $h; $y++) {
                $t = $y / $h;
                $color = imagecolorallocate($image,
                    (int) ($rgb[0] + (147 - $rgb[0]) * $t),
                    (int) ($rgb[1] + (51 - $rgb[1]) * $t),
                    (int) ($rgb[2] + (234 - $rgb[2]) * $t));
                imageline($image, 0, $y, $w, $y, $color);
            }

            $white = imagecolorallocate($image, 255, 255, 255);
            $font = 5;
            $x = (int) (($w - imagefontwidth($font) * strlen($label)) / 2);
            imagestring($image, $font, $x, (int) ($h / 2) - 8, $label, $white);

            ob_start();
            imagewebp($image, null, 80);
            $path = "photos/{$userId}/sample-".Str::random(10).($key === 'path_sm' ? '-sm' : '').'.webp';
            Storage::disk('local')->put($path, (string) ob_get_clean());
            imagedestroy($image);

            $paths[$key] = $path;
        }

        return $paths;
    }
}
