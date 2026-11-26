<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Queries;

use AIArmada\Affiliates\Features\Support\Actions\OpenSupportTicket;
use AIArmada\Affiliates\Features\Support\Actions\ReplyToSupportTicket;
use AIArmada\Affiliates\Features\Support\Actions\UpdateSupportTicket;
use AIArmada\Affiliates\Features\Support\Api\SupportTicketApi;

final readonly class SupportTicketGateway implements SupportTicketApi
{
    public function __construct(private OpenSupportTicket $openTicket, private ReplyToSupportTicket $reply, private UpdateSupportTicket $updateTicket, private SupportTicketQuery $query) {}

    public function open(string $affiliateId, string $subject, string $category, string $priority, ?string $message = null, string $status = 'open'): string
    {
        return $this->openTicket->execute($affiliateId, $subject, $category, $priority, $message, $status);
    }

    public function replyAsAffiliate(string $affiliateId, string $ticketId, string $message): bool
    {
        return $this->reply->asAffiliate($affiliateId, $ticketId, $message);
    }

    public function replyAsStaff(string $ticketId, string $staffId, string $message): string
    {
        return $this->reply->asStaff($ticketId, $staffId, $message);
    }

    public function update(string $ticketId, string $affiliateId, string $subject, string $category, string $priority, string $status): void
    {
        $this->updateTicket->execute($ticketId, $affiliateId, $subject, $category, $priority, $status);
    }

    public function ticketsFor(string $affiliateId): array
    {
        return $this->query->forAffiliate($affiliateId);
    }
}
