<?php

use App\Enums\ReportStatus;
use App\Livewire\Concerns\ModeratesMembers;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Reports')] class extends Component {
    use ModeratesMembers, WithPagination;

    #[Url]
    public string $status = 'open';

    #[Computed]
    public function reports()
    {
        return Report::query()
            ->where('status', ReportStatus::tryFrom($this->status) ?? ReportStatus::Open)
            ->with(['reporter.profile', 'reported.profile', 'message'])
            ->latest()
            ->paginate(15);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function close(int $reportId, string $status): void
    {
        $report = Report::findOrFail($reportId);
        $report->close(
            ReportStatus::from($status === 'dismissed' ? 'dismissed' : 'resolved'),
            Auth::user(),
            $status === 'dismissed' ? __('No action needed') : __('Action taken'),
        );

        unset($this->reports);
    }

    protected function afterModeration(): void
    {
        unset($this->reports);
    }
}; ?>

<section class="w-full">
    @include('partials.admin-nav', ['heading' => __('Reports')])

    <flux:radio.group wire:model.live="status" variant="segmented" class="mb-4 max-w-sm">
        <flux:radio value="open" :label="__('Open')" />
        <flux:radio value="resolved" :label="__('Resolved')" />
        <flux:radio value="dismissed" :label="__('Dismissed')" />
    </flux:radio.group>

    <div class="space-y-4">
        @forelse ($this->reports as $report)
            @php($member = $report->reported)
            @php($previous = \App\Models\Report::where('reported_id', $member->id)->count())
            <flux:card wire:key="report-{{ $report->id }}" class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge :color="$report->reason === \App\Enums\ReportReason::Scam || $report->reason === \App\Enums\ReportReason::Underage ? 'red' : 'amber'">{{ $report->reason->label() }}</flux:badge>
                    <flux:text class="text-sm">{{ $report->created_at->diffForHumans() }}</flux:text>
                    @if ($previous > 1)
                        <flux:badge color="red" size="sm">{{ __(':n reports in total', ['n' => $previous]) }}</flux:badge>
                    @endif
                </div>

                <flux:text>
                    <strong>{{ __('Reported:') }}</strong> {{ $member->displayName() }} ({{ $member->email }}, {{ $member->age() }})
                    · <strong>{{ __('By:') }}</strong> {{ $report->reporter?->displayName() ?? __('deleted account') }}
                </flux:text>

                @if ($report->details)
                    <flux:text class="whitespace-pre-line">“{{ $report->details }}”</flux:text>
                @endif

                @if ($report->message)
                    <div class="rounded-lg bg-zinc-100 p-3 text-sm dark:bg-zinc-800">
                        <span class="text-zinc-500">{{ __('Reported message:') }}</span> {{ $report->message->body }}
                    </div>
                @endif

                @if ($report->status === \App\Enums\ReportStatus::Open)
                    <div class="flex flex-wrap items-center gap-2">
                        @include('partials.admin-member-actions', ['member' => $member])
                        <flux:button size="sm" variant="primary" wire:click="close({{ $report->id }}, 'resolved')">{{ __('Mark resolved') }}</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="close({{ $report->id }}, 'dismissed')">{{ __('Dismiss') }}</flux:button>
                    </div>
                @else
                    <flux:text class="text-sm">{{ $report->resolution }}</flux:text>
                @endif
            </flux:card>
        @empty
            <flux:card class="py-10 text-center"><flux:text>{{ __('Nothing here.') }}</flux:text></flux:card>
        @endforelse

        {{ $this->reports->links() }}
    </div>
</section>
