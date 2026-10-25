<?php

namespace App\Http\Controllers;

use App\Enums\ModerationStatus;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves member photos from the private disk. A photo is shown to its owner,
 * to admins, or to signed-in members when it is approved, the owner is
 * discoverable and neither person has blocked the other.
 */
class PhotoController extends Controller
{
    public function __invoke(Request $request, Photo $photo, string $size): StreamedResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $allowed = $viewer->is_admin
            || $photo->user_id === $viewer->id
            || ($photo->status === ModerationStatus::Approved
                && User::query()->discoverable()->whereKey($photo->user_id)->exists()
                && ! $viewer->hasBlockBetween($photo->user));

        abort_unless($allowed, 404);

        $path = $size === 'sm' ? $photo->path_sm : $photo->path;

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
