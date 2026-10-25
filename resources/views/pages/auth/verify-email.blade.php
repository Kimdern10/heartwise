<x-layouts::auth :title="__('Verify your email')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Check your inbox')"
            :description="__('We sent a 6-digit code to :email. Enter it below to verify your email.', ['email' => auth()->user()->email])" />

        <livewire:email-code-form />

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <flux:button variant="ghost" size="sm" type="submit" class="cursor-pointer" data-test="logout-button">
                {{ __('Use a different email (log out)') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
