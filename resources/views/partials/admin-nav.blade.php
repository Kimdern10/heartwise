{{-- Admin page heading. The admin menu itself is in the sidebar (layouts/app/sidebar). --}}
<div class="mb-6 animate-fade-up">
    <flux:heading size="xl" level="1" class="font-display !text-4xl">{{ $heading ?? __('Admin overview') }}</flux:heading>
    <flux:subheading>{{ __('Keep the community real and safe. Every decision is final for the member, so check carefully.') }}</flux:subheading>
</div>
