<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Services;

use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Contracts\TicketableInterface;
use AIArmada\Ticketing\Events\PassIssued;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class DefaultPassIssuer implements PassIssuerInterface
{
    public function issuePassesFor(PassIssuanceContext $context): Collection
    {
        $passes = new Collection;
        $ticketable = $context->ticketType->ticketable;

        for ($i = 0; $i < $context->quantity; $i++) {
            $pass = new Pass;
            $pass->ticketable_type = $ticketable?->getMorphClass() ?? $context->ticketType->ticketable_type;
            $pass->ticketable_id = $ticketable?->getKey() ?? $context->ticketType->ticketable_id;
            $pass->ticket_type_id = $context->ticketType->getKey();
            $pass->pass_no = $this->generatePassNo();
            $pass->qr_code = (string) Str::uuid();
            $pass->barcode = Str::random(16);
            $pass->status = 'issued';
            $pass->issued_at = $context->issuedAt;
            $pass->metadata = $context->metadata;

            if ($ticketable instanceof TicketableInterface) {
                $pass->transfer_expires_at = $ticketable->transferWindowEndsAt();
            }

            $pass->save();

            event(new PassIssued($pass, $context->issuedAt));

            $passes->push($pass);
        }

        return $passes;
    }

    private function generatePassNo(): string
    {
        $prefix = config('ticketing.defaults.pass_no_prefix', 'PASS-');

        do {
            $passNo = $prefix . mb_strtoupper(Str::random(8));
        } while (Pass::query()->withoutOwnerScope()->where('pass_no', $passNo)->exists());

        return $passNo;
    }
}
