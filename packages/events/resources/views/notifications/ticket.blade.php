<x-mail::message>
# Your Ticket for {{ $eventName }}

Hi, your ticket for **{{ $brandName }} : {{ $eventName }}** is ready.

**Ticket No:** {{ $passNo }}

<x-mail::button :url="config('app.url')">
View Your Ticket
</x-mail::button>

Please keep this ticket number for check-in at the event.

Thanks,<br>
{{ $brandName }}
</x-mail::message>
