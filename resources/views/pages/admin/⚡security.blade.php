<?php

use App\Livewire\Concerns\ModeratesMembers;
use App\Models\ConnectionCheck;
use App\Models\Setting;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Connection security')] class extends Component {
    use ModeratesMembers, WithPagination;

    public string $vpnPolicy = 'flag';
    public bool $usOnly = false;
    public string $blockedCountries = '';
    public string $allowedCountries = '';
    public bool $flagTimezone = true;
    public string $blockedIps = '';
    public string $allowedIps = '';

    #[Url]
    public string $show = 'flagged';

    public function mount(): void
    {
        $this->vpnPolicy = Setting::get('vpn_policy');
        $this->usOnly = Setting::get('us_only') === '1';
        $this->blockedCountries = Setting::get('blocked_countries');
        $this->allowedCountries = Setting::get('allowed_countries');
        $this->flagTimezone = Setting::get('flag_timezone_mismatch') === '1';
        $this->blockedIps = Setting::get('blocked_ips');
        $this->allowedIps = Setting::get('allowed_ips');
    }

    public function save(): void
    {
        $this->validate([
            'vpnPolicy' => ['required', Rule::in(['off', 'flag', 'block'])],
            'blockedCountries' => ['nullable', 'regex:/^[A-Za-z]{2}(\s*,\s*[A-Za-z]{2})*$/'],
            'allowedCountries' => ['nullable', 'regex:/^[A-Za-z]{2}(\s*,\s*[A-Za-z]{2})*$/'],
            'blockedIps' => ['nullable', 'string', 'max:5000'],
            'allowedIps' => ['nullable', 'string', 'max:5000'],
        ], [
            'blockedCountries.regex' => __('Use 2-letter country codes separated by commas, e.g. NG, GH.'),
            'allowedCountries.regex' => __('Use 2-letter country codes separated by commas, e.g. CA, GB.'),
        ]);

        foreach (['blockedIps', 'allowedIps'] as $field) {
            foreach (array_filter(array_map('trim', explode(',', $this->{$field}))) as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                    $this->addError($field, __(':ip is not a valid IP address.', ['ip' => $ip]));

                    return;
                }
            }
        }

        Setting::put('vpn_policy', $this->vpnPolicy);
        Setting::put('us_only', $this->usOnly ? '1' : '0');
        Setting::put('blocked_countries', strtoupper($this->blockedCountries));
        Setting::put('allowed_countries', strtoupper($this->allowedCountries));
        Setting::put('flag_timezone_mismatch', $this->flagTimezone ? '1' : '0');
        Setting::put('blocked_ips', $this->blockedIps);
        Setting::put('allowed_ips', $this->allowedIps);
        Setting::put('policy_version', (string) now()->timestamp);

        Flux::toast(variant: 'success', text: __('Security rules saved. They apply immediately.'));
    }

    public function blockIp(string $ip): void
    {
        $this->addToList('blocked_ips', $ip, false);
        $this->blockedIps = Setting::get('blocked_ips');
        Flux::toast(variant: 'success', text: __(':ip blocked.', ['ip' => $ip]));
    }

    public function blockCountry(string $country): void
    {
        $this->addToList('blocked_countries', $country, true);
        $this->blockedCountries = Setting::get('blocked_countries');
        Flux::toast(variant: 'success', text: __('Country :c blocked.', ['c' => $country]));
    }

    private function addToList(string $key, string $value, bool $upper): void
    {
        $list = Setting::list($key, $upper);
        $list[] = $upper ? strtoupper($value) : $value;
        Setting::put($key, implode(', ', array_unique($list)));
        Setting::put('policy_version', (string) now()->timestamp);
    }

    #[Computed]
    public function checks()
    {
        return ConnectionCheck::query()
            ->with('user.profile')
            ->when($this->show === 'flagged', fn ($q) => $q->where(fn ($q) => $q->where('is_vpn', true)->orWhere('is_hosting', true)->orWhere('timezone_mismatch', true)))
            ->when($this->show === 'blocked', fn ($q) => $q->where('blocked', true))
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function stats(): array
    {
        $since = now()->subDays(7);

        return [
            __('VPN / proxy (7 days)') => ConnectionCheck::where('created_at', '>=', $since)->where('is_vpn', true)->count(),
            __('Data-centre IPs (7 days)') => ConnectionCheck::where('created_at', '>=', $since)->where('is_hosting', true)->count(),
            __('Timezone mismatches (7 days)') => ConnectionCheck::where('created_at', '>=', $since)->where('timezone_mismatch', true)->count(),
            __('Blocked (7 days)') => ConnectionCheck::where('created_at', '>=', $since)->where('blocked', true)->count(),
        ];
    }

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    protected function afterModeration(): void
    {
        unset($this->checks);
    }
}; ?>

<section class="w-full">
    @include('partials.admin-nav', ['heading' => __('Connection security')])

    <flux:callout icon="information-circle" class="mb-6">
        <flux:callout.text>
            {{ __('No website can see someone’s real location through a VPN. We detect VPN, proxy and data-centre connections, and compare each device’s clock (timezone) with its IP location — a computer set to West Africa time on a “Texas” IP is a classic romance-scam sign. Device GPS checks on profiles add a third signal.') }}
        </flux:callout.text>
    </flux:callout>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->stats as $label => $value)
            <flux:card><flux:text>{{ $label }}</flux:text><flux:heading size="xl" class="mt-1">{{ $value }}</flux:heading></flux:card>
        @endforeach
    </div>

    <form wire:submit="save" class="hw-card mb-8 grid gap-6 p-6 lg:grid-cols-2">
        <div class="space-y-4">
            <flux:radio.group wire:model="vpnPolicy" :label="__('VPN / proxy connections')">
                <flux:radio value="off" :label="__('Allow (don’t flag)')" />
                <flux:radio value="flag" :label="__('Allow, but flag the member for review')" />
                <flux:radio value="block" :label="__('Block — ask them to turn the VPN off')" />
            </flux:radio.group>

            <flux:switch wire:model="flagTimezone" :label="__('Flag devices set to a non-US timezone')" />
            <flux:switch wire:model="usOnly" :label="__('US only — block connections from other countries')"
                :description="__('Tip: add your own IP to “Always allow” first, so you don’t lock yourself out.')" />
        </div>

        <div class="space-y-4">
            <flux:input wire:model="blockedCountries" :label="__('Blocked countries')" placeholder="NG, GH" :description:trailing="__('2-letter codes, comma separated.')" />
            <flux:input wire:model="allowedCountries" :label="__('Also allow (when US only is on)')" placeholder="CA" />
            <flux:textarea wire:model="blockedIps" rows="2" :label="__('Blocked IP addresses')" />
            <flux:textarea wire:model="allowedIps" rows="2" :label="__('Always allow these IP addresses')" :placeholder="__('e.g. your own IP: ') . request()->ip()" />
        </div>

        <div class="flex items-center justify-between lg:col-span-2">
            <flux:text class="text-xs">{{ __('Your current IP: :ip. Admins are never blocked once logged in.', ['ip' => request()->ip()]) }}</flux:text>
            <flux:button type="submit" variant="primary" data-test="save-security">{{ __('Save rules') }}</flux:button>
        </div>
    </form>

    <flux:radio.group wire:model.live="show" variant="segmented" class="mb-4 max-w-md">
        <flux:radio value="flagged" :label="__('Flagged')" />
        <flux:radio value="blocked" :label="__('Blocked')" />
        <flux:radio value="all" :label="__('All')" />
    </flux:radio.group>

    <flux:table :paginate="$this->checks">
        <flux:table.columns>
            <flux:table.column>{{ __('Member') }}</flux:table.column>
            <flux:table.column>{{ __('IP location') }}</flux:table.column>
            <flux:table.column>{{ __('Device timezone') }}</flux:table.column>
            <flux:table.column>{{ __('Profile / GPS') }}</flux:table.column>
            <flux:table.column>{{ __('Signals') }}</flux:table.column>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->checks as $check)
                <flux:table.row :key="$check->id">
                    <flux:table.cell>
                        @if ($check->user)
                            {{ $check->user->displayName() }}<br><span class="text-xs text-zinc-500">{{ $check->user->email }}</span>
                        @else
                            <span class="text-zinc-500">{{ __('Visitor') }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $check->location() }}<br><span class="text-xs text-zinc-500">{{ $check->ip }} · {{ $check->isp }}</span></flux:table.cell>
                    <flux:table.cell>{{ $check->browser_timezone ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($profile = $check->user?->profile)
                            {{ $profile->state }}
                            @if ($profile->isLocationVerified())<flux:badge size="sm" color="green">{{ __('GPS ✓') }}</flux:badge>@endif
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="space-x-1">
                        @if ($check->is_vpn)<flux:badge size="sm" color="red">VPN</flux:badge>@endif
                        @if ($check->is_hosting)<flux:badge size="sm" color="orange">{{ __('Data centre') }}</flux:badge>@endif
                        @if ($check->timezone_mismatch)<flux:badge size="sm" color="amber">{{ __('TZ mismatch') }}</flux:badge>@endif
                        @if ($check->blocked)<flux:badge size="sm" color="zinc">{{ __('Blocked') }}</flux:badge>@endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $check->created_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown align="end">
                            <flux:button size="sm" icon:trailing="chevron-down">{{ __('Action') }}</flux:button>
                            <flux:menu>
                                <flux:menu.item icon="no-symbol" wire:click="blockIp('{{ $check->ip }}')" data-confirm="{{ __('Block IP :ip?', ['ip' => $check->ip]) }}">{{ __('Block this IP') }}</flux:menu.item>
                                @if ($check->country && $check->country !== 'US')
                                    <flux:menu.item icon="globe-americas" wire:click="blockCountry('{{ $check->country }}')" data-confirm="{{ __('Block all connections from :c?', ['c' => $check->country]) }}">{{ __('Block country :c', ['c' => $check->country]) }}</flux:menu.item>
                                @endif
                                @if ($check->user && ! $check->user->is_admin && $check->user->isActive())
                                    <flux:menu.separator />
                                    <flux:menu.item icon="pause" wire:click="suspend({{ $check->user_id }}, 7)" data-confirm-danger data-confirm="{{ __('Suspend this member for 7 days?') }}">{{ __('Suspend member') }}</flux:menu.item>
                                    <flux:menu.item icon="no-symbol" variant="danger" wire:click="ban({{ $check->user_id }})" data-confirm-danger data-confirm="{{ __('Ban this member?') }}">{{ __('Ban member') }}</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
