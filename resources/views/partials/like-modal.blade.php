{{-- The "send a like" sheet. The parent Livewire component uses the LikesProfiles trait. --}}
@php($ctx = $this->likeContext())

<flux:modal name="like-item" class="w-full max-w-md !rounded-[2rem]">
    <form wire:submit="sendLike" class="space-y-5">
        @if ($ctx['photo'])
            <img src="{{ $ctx['photo']->url(true) }}" alt="" class="mx-auto aspect-[4/5] w-48 rounded-2xl object-cover shadow-lg" />
        @elseif ($ctx['prompt'])
            <div class="rounded-2xl bg-zinc-50 p-5 dark:bg-zinc-800">
                <p class="text-sm font-semibold text-zinc-500">{{ $ctx['prompt']->prompt }}</p>
                <p class="font-display mt-2 text-2xl leading-snug">{{ $ctx['prompt']->answer }}</p>
            </div>
        @else
            <flux:heading size="lg" class="font-display text-center !text-2xl">{{ __('Like :name?', ['name' => $ctx['name']]) }}</flux:heading>
        @endif

        <flux:textarea wire:model="likeComment" rows="2" maxlength="300" :placeholder="__('Add a comment (optional) — it makes a great first message')" />
        <flux:error name="likeComment" />

        @if (auth()->user()->hasSparkLeftToday())
            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-950/40">
                <input type="checkbox" wire:model="likeSpark" class="size-4 accent-amber-500" />
                <flux:icon.bolt variant="solid" class="size-5 text-amber-500" />
                <span class="text-sm"><strong>{{ __('Send as a Spark') }}</strong> — {{ __('stand out at the top of their likes. 1 free per day.') }}</span>
            </label>
        @endif

        <div class="flex gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary" icon="heart" class="flex-1" data-test="send-like">{{ __('Send like') }}</flux:button>
        </div>
    </form>
</flux:modal>
