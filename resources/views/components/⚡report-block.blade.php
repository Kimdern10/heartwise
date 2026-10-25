<?php

use App\Actions\Dating\BlockUser;
use App\Actions\Dating\ReportUser;
use App\Enums\ReportReason;
use App\Exceptions\DatingActionException;
use App\Models\Message;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $userId;

    #[Locked]
    public ?int $messageId = null;

    public string $reason = '';

    public string $details = '';

    public bool $alsoBlock = true;

    public function report(ReportUser $reportUser): void
    {
        $this->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $reportUser->handle(
                Auth::user(),
                User::findOrFail($this->userId),
                ReportReason::from($this->reason),
                $this->details ?: null,
                $this->messageId ? Message::find($this->messageId) : null,
                $this->alsoBlock,
            );
        } catch (DatingActionException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        Flux::modal('report-'.$this->userId)->close();
        Flux::toast(variant: 'success', text: __('Thank you. Our team will review this report. The person is not told who reported them.'));

        if ($this->alsoBlock) {
            $this->redirectRoute('discover', navigate: true);
        }
    }

    public function block(BlockUser $blockUser): void
    {
        $blockUser->handle(Auth::user(), User::findOrFail($this->userId));

        Flux::toast(text: __('Blocked. You will no longer see each other.'));

        $this->redirectRoute('discover', navigate: true);
    }
}; ?>

<div class="flex flex-wrap gap-2">
    <flux:modal.trigger :name="'report-'.$userId">
        <flux:button size="sm" variant="ghost" icon="flag">{{ __('Report') }}</flux:button>
    </flux:modal.trigger>

    <flux:button size="sm" variant="ghost" icon="no-symbol"
        wire:click="block"
        data-confirm-danger data-confirm="{{ __('Block this person? You will no longer see each other or be able to message.') }}"
        data-test="block-button">
        {{ __('Block') }}
    </flux:button>

    <flux:modal :name="'report-'.$userId" class="w-full max-w-md">
        <form wire:submit="report" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Report this member') }}</flux:heading>
                <flux:text>{{ __('Reports are confidential. If you are in danger, call 911 first.') }}</flux:text>
            </div>

            <flux:radio.group wire:model="reason" :label="__('What is wrong?')">
                @foreach (\App\Enums\ReportReason::cases() as $option)
                    <flux:radio :value="$option->value" :label="$option->label()" />
                @endforeach
            </flux:radio.group>

            <flux:textarea wire:model="details" :label="__('Anything else we should know? (optional)')" rows="3" maxlength="2000" />

            <flux:checkbox wire:model="alsoBlock" :label="__('Also block this person')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" data-test="send-report">{{ __('Send report') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
