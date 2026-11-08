<x-mail::message>
# {{ __('Welcome to :app!', ['app' => config('app.name')]) }}

{{ __('Enter this code to verify your email address:') }}

<span class="code">{{ $code }}</span>

{{ __('The code expires in 15 minutes.') }}

<p class="safety">{{ __('We will never ask you for this code by phone, chat or email. If someone asks for it, they are trying to get into your account.') }}</p>

{{ __('If you did not create an account, you can ignore this email.') }}

— {{ __('The :app team', ['app' => config('app.name')]) }}
</x-mail::message>
