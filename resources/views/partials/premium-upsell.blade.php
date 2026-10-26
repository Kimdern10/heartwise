{{-- Expects $text. --}}
<a href="{{ route('premium') }}" wire:navigate class="bg-brand-gradient flex items-center gap-4 rounded-3xl p-5 text-white shadow-lg transition hover:scale-[1.005]">
    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white/15"><flux:icon.sparkles variant="solid" class="size-6 text-amber-300" /></span>
    <span class="flex-1 text-sm">{{ $text }}</span>
    <span class="hidden shrink-0 rounded-full bg-white px-4 py-2 text-sm font-semibold text-zinc-900 sm:inline">{{ __('Get Premium') }}</span>
</a>
