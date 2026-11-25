<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventPassIssuanceData;

interface EventTicketingDriver
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function ensureTicketType(string $targetType, string $targetId, array $attributes = []): string;

    public function syncQuota(string $ticketTypeId, int $quantity): void;

    /**
     * @return list<string>
     */
    public function issuePasses(EventPassIssuanceData $issuance): array;

    public function revokePasses(string $registrationType, string $registrationId, ?string $reason = null): int;

    public function countPassesForRegistrationTicket(string $registrationType, string $registrationId, string $ticketTypeId): int;

    /**
     * @param  list<string>  $passIds
     */
    public function revokePassesByIds(array $passIds, string $reason): int;

    /**
     * @param  list<string>  $passIds
     */
    public function deliverPasses(array $passIds): void;

    public function shipInventory(string $ticketTypeId, int $quantity, string $reason, ?string $reference = null): void;

    public function restoreRefundedInventory(
        string $orderId,
        ?string $orderNumber,
        string $ticketTypeId,
        int $quantity,
        string $reason,
    ): void;

    public function restoreRefundedRegistrationItemInventory(
        string $orderId,
        ?string $orderNumber,
        string $registrationItemId,
        string $ticketTypeId,
        int $quantity,
    ): void;

    public function deleteScopeChildren(string $scopeType, string $scopeId, ?string $morphClass = null): void;
}
