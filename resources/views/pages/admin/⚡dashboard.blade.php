<?php

use App\Models\Message;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\VerificationRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Moderation')] class extends Component {
    #[Computed]
    public function stats(): array
    {
        return [
            __('Profiles waiting') => [Profile::where('status', 'pending')->count(), route('admin.profiles')],
            __('Photos waiting') => [Photo::where('status', 'pending')->count(), route('admin.photos')],
            __('Selfies waiting') => [VerificationRequest::where('status', 'pending')->count(), route('admin.verifications')],
            __('Open reports') => [Report::where('status', 'open')->count(), route('admin.reports')],
            __('Flagged messages (7 days)') => [Message::whereNotNull('flag')->where('created_at', '>=', now()->subDays(7))->count(), route('admin.messages')],
            __('Members') => [User::where('role', 'member')->count(), route('admin.users')],
            __('Active in last 24h') => [User::where('last_active_at', '>=', now()->subDay())->count(), null],
            __('Matches') => [UserMatch::count(), null],
        ];
    }

    /**
     * Count rows per day for the last 30 days (days with none show as 0).
     *
     * @return array<string, int>
     */
    private function perDay(\Illuminate\Database\Eloquent\Builder $query, string $column = 'created_at'): array
    {
        $since = now()->subDays(29)->startOfDay();
        $counts = $query->where($column, '>=', $since)->get([$column])
            ->groupBy(fn ($row) => $row->{$column}->toDateString())->map->count();

        $days = [];
        for ($d = $since->copy(); $d->lte(now()); $d = $d->addDay()) {
            $days[$d->toDateString()] = (int) ($counts[$d->toDateString()] ?? 0);
        }

        return $days;
    }

    #[Computed]
    public function charts(): array
    {
        return [
            __('New sign-ups') => $this->perDay(User::query()->where('role', 'member')->where('is_sample', false)),
            __('New matches') => $this->perDay(UserMatch::query()),
            __('Premium subscriptions started') => $this->perDay(\Laravel\Cashier\Subscription::query()->where('type', 'premium')->where('stripe_id', 'not like', 'sub_test_local_%')),
            __('Reports received') => $this->perDay(Report::query()),
        ];
    }

    /**
     * Premium headline numbers. Revenue is estimated from active plans
     * (plan price ÷ plan length); Stripe's dashboard has the exact figures.
     */
    #[Computed]
    public function premium(): array
    {
        $plans = collect(config('premium.plans'))->keyBy('price_id');

        $active = \Laravel\Cashier\Subscription::query()
            ->where('type', 'premium')
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->where('stripe_id', 'not like', 'sub_test_local_%')
            ->get();

        $monthly = $active->where('stripe_status', 'active')->sum(fn ($s) => ($plan = $plans[$s->stripe_price] ?? null) ? $plan['amount'] / $plan['months'] : 0);
        $members = max(1, User::where('role', 'member')->where('is_sample', false)->count());

        return [
            __('Premium members') => number_format($active->count()),
            __('On free trial') => number_format($active->where('stripe_status', 'trialing')->count()),
            __('Est. monthly revenue') => '$'.number_format($monthly, 2),
            __('Members on Premium') => round($active->count() / $members * 100, 1).'%',
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.admin-nav')

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @foreach ($this->stats as $label => [$value, $link])
            <flux:card class="max-sm:!p-4">
                <flux:text>{{ $label }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ number_format($value) }}</flux:heading>
                @if ($link && $value)
                    <flux:link :href="$link" wire:navigate class="text-sm">{{ __('Review') }}</flux:link>
                @endif
            </flux:card>
        @endforeach
    </div>

    <h2 class="font-display mt-10 mb-4 text-2xl">{{ __('Premium') }}</h2>
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @foreach ($this->premium as $label => $value)
            <flux:card class="max-sm:!p-4">
                <flux:text>{{ $label }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $value }}</flux:heading>
            </flux:card>
        @endforeach
    </div>
    <flux:text class="mt-2 text-xs">{{ __('Revenue is an estimate from active plans. Stripe’s dashboard has exact payouts, refunds and taxes.') }}</flux:text>

    <h2 class="font-display mt-10 mb-4 text-2xl">{{ __('Last 30 days') }}</h2>
    <div class="grid gap-4 lg:grid-cols-2" data-test="admin-charts">
        @foreach ($this->charts as $title => $series)
            <x-bar-chart :title="$title" :data="$series" :total="number_format(array_sum($series))" />
        @endforeach
    </div>
</section>
