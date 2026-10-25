<?php

use App\Livewire\Concerns\ModeratesMembers;
use App\Models\Message;
use App\Support\MessageScreener;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Flagged messages')] class extends Component {
    use ModeratesMembers, WithPagination;

    #[Computed]
    public function messages()
    {
        return Message::query()
            ->whereNotNull('flag')
            ->where('created_at', '>=', now()->subDays(30))
            ->with('sender.profile')
            ->latest()
            ->paginate(25);
    }

    protected function afterModeration(): void
    {
        unset($this->messages);
    }
}; ?>

<section class="w-full">
    @include('partials.admin-nav', ['heading' => __('Flagged messages')])

    <flux:text class="mb-4">{{ __('Messages from the last 30 days that mention money, other apps, links, phone numbers or look copy-pasted. They were delivered with a warning. Look for patterns, not single mentions.') }}</flux:text>

    <flux:table :paginate="$this->messages">
        <flux:table.columns>
            <flux:table.column>{{ __('Sender') }}</flux:table.column>
            <flux:table.column>{{ __('Why') }}</flux:table.column>
            <flux:table.column>{{ __('Message') }}</flux:table.column>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->messages as $message)
                <flux:table.row :key="$message->id">
                    <flux:table.cell>{{ $message->sender->displayName() }}<br><span class="text-xs text-zinc-500">{{ $message->sender->email }}</span></flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$message->flag === \App\Support\MessageScreener::MONEY ? 'red' : 'amber'">{{ str_replace('_', ' ', $message->flag) }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="max-w-md whitespace-normal">{{ \Illuminate\Support\Str::limit($message->body, 200) }}</flux:table.cell>
                    <flux:table.cell>{{ $message->created_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>@include('partials.admin-member-actions', ['member' => $message->sender])</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
