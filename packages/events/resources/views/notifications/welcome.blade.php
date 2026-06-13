<x-mail::message>
# You're confirmed for {{ $eventName }}

Hi, your registration for **{{ $brandName }} : {{ $eventName }}** is confirmed.

<x-mail::button :url="config('app.url')">
View Event Details
</x-mail::button>

We look forward to seeing you there.

Thanks,<br>
{{ $brandName }}
</x-mail::message>
