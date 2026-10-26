{{-- Big tappable answer buttons. Expects $model (Livewire property) and $options [value => label]. --}}
<div class="grid gap-2.5">
    @foreach ($options as $value => $label)
        @php($selected = (string) $this->{$model} === (string) $value)
        <button type="button" wire:click="$set('{{ $model }}', '{{ $value }}')" wire:key="{{ $model }}-{{ $value }}"
            class="flex items-center justify-between rounded-2xl border-2 px-5 py-4 text-start text-lg font-medium transition active:scale-[.99] {{ $selected ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-200 bg-white hover:border-zinc-400 dark:border-zinc-800 dark:bg-zinc-900' }}">
            {{ $label }}
            @if ($selected)
                <flux:icon.check class="size-5" />
            @endif
        </button>
    @endforeach
</div>
<flux:error :name="$model" />
