<x-mail::message>
# Your checkout is waiting

Hi, you left items in your cart for **{{ $offerName }}**.

@if($preferredDate)
**Session:** {{ $preferredDate }}
@endif

@if($formattedTotal)
**Total:** {{ $formattedTotal }}
@endif

<x-mail::button :url="$retryUrl">
Complete Your Registration
</x-mail::button>

Your reservation is being held but may expire soon.

Thanks,<br>
{{ $brandName }}
</x-mail::message>
