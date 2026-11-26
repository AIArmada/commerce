<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Api;

interface SupportTicketApi
{
    public function open(string $affiliateId, string $subject, string $category, string $priority, ?string $message = null, string $status = 'open'): string;

    public function replyAsAffiliate(string $affiliateId, string $ticketId, string $message): bool;

    public function replyAsStaff(string $ticketId, string $staffId, string $message): string;

    public function update(string $ticketId, string $affiliateId, string $subject, string $category, string $priority, string $status): void;

    /** @return list<array<string, mixed>> */
    public function ticketsFor(string $affiliateId): array;
}
