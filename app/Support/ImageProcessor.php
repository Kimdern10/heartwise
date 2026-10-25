<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Re-encodes uploads with GD. Re-encoding drops all EXIF data (including GPS
 * location), so members never leak where a photo was taken.
 */
class ImageProcessor
{
    /**
     * Save a large and a small WebP copy on the private disk.
     *
     * @return array{path: string, path_sm: string}
     */
    public function storePhoto(UploadedFile $file, string $directory): array
    {
        $image = $this->load($file);
        $name = $directory.'/'.Str::random(40);

        $paths = [
            'path' => $this->save($image, $name.'.webp', 1200),
            'path_sm' => $this->save($image, $name.'-sm.webp', 480),
        ];

        imagedestroy($image);

        return $paths;
    }

    /**
     * Save a single private copy (used for verification selfies).
     */
    public function storeSingle(UploadedFile $file, string $directory, int $maxSide = 1200): string
    {
        $image = $this->load($file);
        $path = $this->save($image, $directory.'/'.Str::random(40).'.webp', $maxSide);
        imagedestroy($image);

        return $path;
    }

    private function load(UploadedFile $file): GdImage
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The file is not a readable image.');
        }

        return $this->orient($image, $file);
    }

    private function orient(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;

        $rotated = match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function save(GdImage $image, string $path, int $maxSide): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxSide / max($width, $height));

        $resized = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

        if (! $resized instanceof GdImage) {
            throw new RuntimeException('Could not resize the image.');
        }

        ob_start();
        imagewebp($resized, null, 82);
        $data = (string) ob_get_clean();
        imagedestroy($resized);

        Storage::disk('local')->put($path, $data);

        return $path;
    }
}
