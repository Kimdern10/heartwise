@props([
    'title',
    'updated' => 'October 25, 2026',
])

<x-layouts::public :title="$title">
    <article class="mx-auto max-w-3xl px-4 py-12">
        @unless (app()->isProduction())
            <flux:callout icon="exclamation-triangle" color="amber" class="mb-8">
                <flux:callout.heading>Draft for review</flux:callout.heading>
                <flux:callout.text>This page is a starting template, not legal advice. Have a US lawyer review it (including CCPA/CPRA and state dating-safety laws) before launch. This notice is hidden in production.</flux:callout.text>
            </flux:callout>
        @endunless

        <h1 class="text-3xl font-bold">{{ $title }}</h1>
        <p class="mt-2 text-sm text-zinc-500">{{ __('Last updated: :date', ['date' => $updated]) }}</p>

        <div class="mt-8 space-y-4 leading-relaxed text-zinc-700 dark:text-zinc-300 [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-zinc-900 dark:[&_h2]:text-white [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:ps-6 [&_a]:text-rose-600 [&_a]:underline">
            {{ $slot }}
        </div>
    </article>
</x-layouts::public>
