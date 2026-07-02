<x-mail::message>
# Pass Transferred To You

You've been transferred a pass.

**Pass Number:** {{ $pass->pass_no }}
**Transferred by:** {{ $previousHolder->name ?? 'previous holder' }}
**Reason:** {{ $reason ?? 'No reason given' }}

Please present this email or the QR code at the entrance.

<x-mail::button :url="url('/')">
View Your Pass
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
