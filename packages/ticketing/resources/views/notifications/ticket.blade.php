<x-mail::message>
# Your Ticket: {{ $pass->pass_no }}

Thank you for your purchase! Here is your ticket.

**Pass Number:** {{ $pass->pass_no }}
**Ticket Type:** {{ $pass->ticketType?->name ?? 'N/A' }}

@if($pass->qr_code)
**QR Code:** {{ $pass->qr_code }}
@endif

@if($pass->barcode)
**Barcode:** {{ $pass->barcode }}
@endif

**Status:** {{ ucfirst($pass->status) }}

@if($pass->holder)
**Holder:** {{ $pass->holder->name }}
**Email:** {{ $pass->holder->email }}
@endif

Please present this email or the QR code at the entrance.

<x-mail::button :url="url('/')">
Visit Event Page
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
