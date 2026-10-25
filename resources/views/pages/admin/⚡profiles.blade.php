<?php

use App\Enums\ModerationStatus;
use App\Models\Profile;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Profile review')] class extends Component {
    use WithPagination;

    /** @var array<int, string> */
    public array $reasons = [];

    #[Computed]
    public function profiles()
    {
        return Profile::query()
            ->where('status', ModerationStatus::Pending)
            ->with(['user.photos'])
            ->oldest('updated_at')
            ->paginate(10);
    }

    public function approve(int $profileId): void
    {
        Profile::findOrFail($profileId)->forceFill(['status' => ModerationStatus::Approved, 'rejection_reason' => null])->save();
        unset($this->profiles);

        Flux::toast(variant: 'success', text: __('Profile approved.'));
    }

    public function reject(int $profileId): void
    {
        $reason = trim($this->reasons[$profileId] ?? '');

        if ($reason === '') {
            $this->addError('reasons.'.$profileId, __('Tell the member what to change.'));

            return;
        }

        Profile::findOrFail($profileId)->forceFill(['status' => ModerationStatus::Rejected, 'rejection_reason' => mb_substr($reason, 0, 250)])->save();
        unset($this->profiles, $this->reasons[$profileId]);

        Flux::toast(text: __('Profile sent back to the member.'));
    }
}; ?>

<section class="w-full">
    @include('partials.admin-nav', ['heading' => __('Profile review')])

    <div class="space-y-4">
        @forelse ($this->profiles as $profile)
            @php($member = $profile->user)
            <flux:card wire:key="profile-{{ $profile->id }}" class="grid gap-4 md:grid-cols-[1fr_2fr]">
                <div class="grid grid-cols-3 gap-2">
                    @forelse ($member->photos as $photo)
                        <img src="{{ $photo->url(true) }}" alt="" class="aspect-square rounded-lg object-cover {{ $photo->status === \App\Enums\ModerationStatus::Pending ? 'ring-2 ring-amber-400' : '' }}" />
                    @empty
                        <flux:text class="col-span-3 text-sm">{{ __('No photos yet.') }}</flux:text>
                    @endforelse
                </div>
                <div class="space-y-3">
                    <div>
                        <flux:heading size="lg">{{ $profile->display_name }}, {{ $member->age() }}</flux:heading>
                        <flux:text class="text-sm">
                            {{ $member->name }} · {{ $member->email }} · {{ __('born') }} {{ $member->date_of_birth?->toFormattedDateString() }} ·
                            {{ __('joined') }} {{ $member->created_at->diffForHumans() }}
                        </flux:text>
                        <flux:text class="text-sm">{{ $profile->gender->label() }} → {{ $profile->seeking->label() }} · {{ $profile->location() }}</flux:text>
                    </div>
                    <flux:text class="whitespace-pre-line">{{ $profile->bio ?: __('(no bio)') }}</flux:text>

                    <div class="flex flex-wrap items-start gap-2">
                        <flux:button variant="primary" size="sm" icon="check" wire:click="approve({{ $profile->id }})" data-test="approve-profile">{{ __('Approve') }}</flux:button>
                        <div class="flex-1">
                            <flux:input size="sm" wire:model="reasons.{{ $profile->id }}" :placeholder="__('Reason, e.g. “Please remove your phone number from your bio”')" />
                            <flux:error name="reasons.{{ $profile->id }}" />
                        </div>
                        <flux:button variant="danger" size="sm" wire:click="reject({{ $profile->id }})">{{ __('Send back') }}</flux:button>
                    </div>
                </div>
            </flux:card>
        @empty
            <flux:card class="py-10 text-center"><flux:text>{{ __('No profiles waiting. 🎉') }}</flux:text></flux:card>
        @endforelse

        {{ $this->profiles->links() }}
    </div>
</section>
