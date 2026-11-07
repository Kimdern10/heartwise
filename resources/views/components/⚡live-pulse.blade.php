<?php

use App\Models\Like;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Checks every few seconds (even in a background tab) for new messages,
 * likes and matches. It updates the sidebar badges and the bell, and pops
 * up a SweetAlert, so members never need to refresh to find out.
 * Lives once in the app layout (kept alive across page changes).
 */
new class extends Component {
    #[Locked]
    public string $since = '';

    /** @var array<string, int> */
    #[Locked]
    public array $counts = [];

    public function mount(): void
    {
        $this->since = now()->toDateTimeString();
    }

    public function check(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $since = $this->since;
        $this->since = now()->toDateTimeString();

        // Anything new since the last check pops up (at most 3 at once).
        $new = $user->unreadNotifications()
            ->where('created_at', '>', $since)
            ->oldest()
            ->limit(3)
            ->get();

        foreach ($new as $notification) {
            $this->dispatch('live-alert',
                kind: $notification->data['kind'] ?? 'info',
                title: $notification->data['title'] ?? '',
                body: $notification->data['body'] ?? '',
                url: $notification->data['url'] ?? route('dashboard'),
            );
        }

        $counts = [
            'messages' => $user->unreadMessagesCount(),
            'likes' => Like::query()->where('target_id', $user->id)->where('liked', true)
                ->whereNotIn('user_id', Like::query()->where('user_id', $user->id)->select('target_id'))
                ->count(),
            'bell' => $user->unreadNotifications()->count(),
        ];

        // Only tell the page when something changed, to keep the site light.
        if ($counts !== $this->counts) {
            $this->counts = $counts;
            $this->dispatch('live-counts', ...$counts);
        }
    }
}; ?>

<div wire:poll.5s.keep-alive="check" class="hidden" aria-hidden="true"></div>
