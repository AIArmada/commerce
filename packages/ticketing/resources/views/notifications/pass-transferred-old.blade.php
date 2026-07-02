<x-mail::message>
# Pass Transferred Away

Your pass has been transferred to someone else.

**Pass Number:** {{ $pass->pass_no }}
**Transferred to:** {{ $newHolder->name ?? 'another person' }}
**Reason:** {{ $reason ?? 'No reason given' }}

You no longer have access with this pass.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
