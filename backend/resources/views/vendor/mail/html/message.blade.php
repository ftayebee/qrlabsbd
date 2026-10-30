<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.frontend_url')">
{{ config('qrio.brand.name') }}
</x-mail::header>
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
© {{ date('Y') }} [{{ config('qrio.brand.name') }}]({{ config('app.frontend_url') }}). {{ __('All rights reserved.') }}
@if (config('qrio.brand.support_email'))
<br>{{ __('Questions? Reply to this email or write to') }} [{{ config('qrio.brand.support_email') }}](mailto:{{ config('qrio.brand.support_email') }}).
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
