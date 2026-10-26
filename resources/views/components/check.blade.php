@props(['label'])

{{-- A plain checkbox that binds cleanly to array properties (wire:model="items" value="x"). --}}
<label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-zinc-700 select-none dark:text-zinc-300">
    <input type="checkbox" {{ $attributes->class('size-4 rounded border-zinc-300 accent-zinc-900 dark:accent-white') }} />
    <span>{{ $label }}</span>
</label>
