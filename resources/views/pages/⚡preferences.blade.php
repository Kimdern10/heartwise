<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dating preferences')] class extends Component {
    public int $ageMin = 18;
    public int $ageMax = 99;
    public string $maxMiles = '';

    /** @var list<string> */
    public array $dealbreakers = [];

    /** @var array<string, list<string>> */
    public array $values = [];

    public const PREFERABLE = ['religion', 'has_kids', 'wants_kids', 'drinking', 'smoking'];

    public function mount(): void
    {
        $profile = Auth::user()->profile;
        $age = Auth::user()->age() ?? 30;

        $this->ageMin = $profile->pref_age_min ?? max(18, $age - 8);
        $this->ageMax = $profile->pref_age_max ?? min(99, $age + 8);
        $this->maxMiles = (string) ($profile->pref_max_miles ?? '');
        $this->dealbreakers = $profile->dealbreakers ?? [];

        foreach (self::PREFERABLE as $key) {
            $this->values[$key] = $profile->preferred($key);
        }
    }

    public function save(): void
    {
        $rules = [
            'ageMin' => ['required', 'integer', 'min:18', 'max:99', 'lte:ageMax'],
            'ageMax' => ['required', 'integer', 'min:18', 'max:99'],
            'maxMiles' => ['nullable', 'integer', 'min:5', 'max:500'],
            'dealbreakers' => ['array'],
            'dealbreakers.*' => [Rule::in(config('profile_options.dealbreakers'))],
        ];

        foreach (self::PREFERABLE as $key) {
            $rules["values.$key"] = ['array'];
            $rules["values.$key.*"] = [Rule::in(array_keys(config("profile_options.vitals.$key.options")))];
        }

        $this->validate($rules, ['ageMin.lte' => __('The minimum age must be lower than the maximum.')]);

        Auth::user()->profile->forceFill([
            'pref_age_min' => $this->ageMin,
            'pref_age_max' => $this->ageMax,
            'pref_max_miles' => $this->maxMiles !== '' ? (int) $this->maxMiles : null,
            'dealbreakers' => array_values(array_unique($this->dealbreakers)),
            'pref_values' => array_filter($this->values),
        ])->save();

        Flux::toast(variant: 'success', text: __('Preferences saved. Discover now uses them.'));
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <div class="animate-fade-up">
        <flux:heading size="xl" level="1" class="font-display !text-4xl">{{ __('Dating preferences') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Preferences shape your compatibility. Turn on “Dealbreaker” to never see people outside them.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="hw-card space-y-4 p-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Age range') }}</flux:heading>
                <x-check wire:model="dealbreakers" value="age" :label="__('Dealbreaker')" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <flux:input type="number" wire:model="ageMin" min="18" max="99" :label="__('From')" />
                <flux:input type="number" wire:model="ageMax" min="18" max="99" :label="__('To')" />
            </div>
            <flux:error name="ageMin" />
        </div>

        <div class="hw-card space-y-4 p-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Maximum distance') }}</flux:heading>
                <x-check wire:model="dealbreakers" value="distance" :label="__('Dealbreaker')" />
            </div>
            <flux:select wire:model="maxMiles">
                <flux:select.option value="">{{ __('Anywhere in the US') }}</flux:select.option>
                @foreach ([10, 25, 50, 100, 250] as $miles)
                    <flux:select.option :value="$miles">{{ __('Within :n miles', ['n' => $miles]) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:text class="text-xs">{{ __('Distance only works with members who verified their location (you too).') }}</flux:text>
        </div>

        @foreach (self::PREFERABLE as $key)
            @php($vital = config("profile_options.vitals.$key"))
            <div class="hw-card space-y-4 p-6" wire:key="pref-{{ $key }}">
                <div class="flex items-center justify-between gap-4">
                    <flux:heading size="lg">{{ __($vital['label']) }}</flux:heading>
                    <x-check wire:model="dealbreakers" :value="$key" :label="__('Dealbreaker')" />
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    @foreach ($vital['options'] as $value => $label)
                        <x-check wire:model="values.{{ $key }}" :value="$value" :label="$label" />
                    @endforeach
                </div>
                <flux:text class="text-xs">{{ __('Leave all unticked for “open to anyone”.') }}</flux:text>
            </div>
        @endforeach

        <div class="hw-card flex items-center justify-between gap-4 p-6">
            <div>
                <flux:heading size="lg">{{ __('Verified members only') }}</flux:heading>
                <flux:text>{{ __('Only show people with the selfie badge.') }}</flux:text>
            </div>
            <x-check wire:model="dealbreakers" value="verified" :label="__('Dealbreaker')" />
        </div>

        <div class="sticky bottom-24 flex justify-end lg:bottom-4">
            <flux:button type="submit" variant="primary" class="shadow-xl" data-test="save-preferences">{{ __('Save preferences') }}</flux:button>
        </div>
    </form>
</section>
