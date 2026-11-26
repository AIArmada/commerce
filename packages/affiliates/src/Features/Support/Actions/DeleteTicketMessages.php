<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Actions;

use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportMessage;

final readonly class DeleteTicketMessages
{
    public function execute(string $ticketId): void
    {
        AffiliateSupportMessage::query()->where('ticket_id', $ticketId)->delete();
    }
}
