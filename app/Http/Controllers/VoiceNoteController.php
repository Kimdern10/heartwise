<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plays a voice note from the private disk: only the two people in the chat
 * (while it is still active) and moderators can hear it.
 */
class VoiceNoteController extends Controller
{
    public function __invoke(Request $request, Message $message): StreamedResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $match = $message->match;

        $allowed = $viewer->isStaff()
            || ($match->includes($viewer) && $match->isActive() && ! $viewer->hasBlockBetween($match->otherUser($viewer)));

        abort_unless($allowed && $message->audio_path && Storage::disk('local')->exists($message->audio_path), 404);

        return Storage::disk('local')->response($message->audio_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
