<x-mail::layout>
{{-- Header: logo + name --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" />
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ __('Never send money, gift cards or crypto to someone you met online.') }}

[{{ __('Safety tips') }}]({{ route('legal.safety') }}) · [{{ __('Privacy') }}]({{ route('legal.privacy') }}) · [{{ __('Email settings') }}]({{ route('privacy.edit') }}#notifications)

© {{ date('Y') }} {{ config('app.name') }} · {{ __('Real dating for adults 18+ in the United States.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
