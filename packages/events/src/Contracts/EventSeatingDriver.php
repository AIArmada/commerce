<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventSeatingDriver
{
    public function hasActiveSeatMap(string $scopeType, string $scopeId): bool;

    /**
     * @return list<string>
     */
    public function holdSeats(
        string $scopeType,
        string $scopeId,
        string $seatingMode,
        int $quantity,
        ?string $heldByType = null,
        ?string $heldById = null,
    ): array;

    /**
     * @return list<string>
     */
    public function allocateSeats(
        string $scopeType,
        string $scopeId,
        string $seatingMode,
        string $ticketTypeId,
        string $allocatedToType,
        string $allocatedToId,
        ?string $reference = null,
    ): array;

    public function allocatePassSeats(string $passId): void;

    public function resolveScopeSeatMapId(string $scopeType, string $scopeId): ?string;

    public function releaseAllocations(string $allocatedToType, string $allocatedToId): int;

    public function deleteScopeChildren(string $scopeType, string $scopeId, ?string $morphClass = null): void;
}
