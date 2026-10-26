{{-- Expects $icon, $title, $text. --}}
<div class="hw-card mx-auto max-w-md px-6 py-14 text-center animate-pop">
    <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-900 text-white animate-float dark:bg-white dark:text-zinc-900">
        <flux:icon :name="$icon" class="size-8" />
    </div>
    <flux:heading size="lg" class="font-display mt-5 !text-2xl">{{ $title }}</flux:heading>
    <flux:text class="mt-1">{{ $text }}</flux:text>
</div>
