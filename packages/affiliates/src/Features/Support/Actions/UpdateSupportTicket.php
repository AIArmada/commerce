<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Actions;

use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class UpdateSupportTicket
{
    public function execute(string $ticketId, string $affiliateId, string $subject, string $category, string $priority, string $status): void
    {
        TicketInput::validate($subject, $category, $priority, $status);

        (new AffiliateSupportTicket)->getConnection()->transaction(function () use ($ticketId, $affiliateId, $subject, $category, $priority, $status): void {
            $ticket = AffiliateSupportTicket::query()->whereKey($ticketId)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $ticket);

            if ((string) $ticket->affiliate_id !== $affiliateId) {
                throw new InvalidArgumentException('A support ticket cannot be reassigned to another affiliate.');
            }

            $ticket->update(compact('subject', 'category', 'priority', 'status'));
        });
    }
}
