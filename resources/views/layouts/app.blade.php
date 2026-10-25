<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="!bg-transparent pb-28 lg:pb-8">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
