<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Download my data" — a copy of what we hold about the member (US privacy laws such as the CCPA give a right to know).
 */
class DataExportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['profile', 'photos', 'likesGiven', 'blocks', 'verificationRequests']);

        $matchIds = UserMatch::query()->involving($user)->pluck('id');

        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => $user->only(['name', 'email', 'date_of_birth', 'email_verified_at', 'terms_accepted_at', 'created_at', 'last_active_at']),
            'profile' => $user->profile?->only([
                'display_name', 'gender', 'seeking', 'relationship_goal', 'bio', 'city', 'state',
                'height_cm', 'status', 'photo_verified_at', 'is_visible', 'show_last_active',
            ]),
            'photos' => $user->photos->map->only(['id', 'status', 'position', 'created_at']),
            'likes_and_passes' => $user->likesGiven->map->only(['target_id', 'liked', 'created_at']),
            'blocked_member_ids' => $user->blocks->pluck('blocked_id'),
            'verification_requests' => $user->verificationRequests->map->only(['status', 'created_at', 'reviewed_at']),
            'matches' => UserMatch::query()->whereIn('id', $matchIds)->get(['id', 'created_at']),
            'messages_sent' => Message::query()->where('sender_id', $user->id)->get(['match_id', 'body', 'created_at']),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="my-'.str(config('app.name'))->slug().'-data.json"',
        ], JSON_PRETTY_PRINT);
    }
}
