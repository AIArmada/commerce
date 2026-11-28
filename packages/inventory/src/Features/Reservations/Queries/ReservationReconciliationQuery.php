<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Queries;

use AIArmada\Inventory\Features\Reservations\Api\ReservationReconciliation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryOperation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryReservation;
use AIArmada\Inventory\Features\Stock\Api\MovementReferences;
use AIArmada\Inventory\Support\InventoryOwnerScope;

final readonly class ReservationReconciliationQuery implements ReservationReconciliation
{
    public function __construct(private MovementReferences $movements) {}

    /**
     * Return durable operation and reservation bookkeeping indicators.
     *
     * @return array{operations_total: int, operations_pending: int, operations_failed: int, completed_operations_without_movement: int, reservations_total: int, reservations_expired_pending_cleanup: int, reservations_without_allocations: int, terminal_reservations_with_allocations: int}
     */
    public function getSummary(): array
    {
        $operations = InventoryOwnerScope::applyToLocationQuery(InventoryOperation::query());
        $operationCounts = (clone $operations)->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $completedWithoutMovement = 0;
        $ownerScoped = InventoryOwnerScope::isEnabled();

        (clone $operations)
            ->where('status', InventoryOperation::STATUS_COMPLETED)
            ->select(['id', 'order_id', 'owner_type', 'owner_id'])
            ->toBase()
            ->chunkById(200, function ($rows) use (&$completedWithoutMovement, $ownerScoped): void {
                $references = $rows->map(static fn ($row): string => (string) $row->order_id)->values()->all();
                $matches = [];

                foreach ($this->movements->matching($references) as $movement) {
                    $matches[$this->identity($movement['reference'], $movement['owner_type'], $movement['owner_id'], $ownerScoped)] = true;
                }

                foreach ($rows as $row) {
                    $key = $this->identity(
                        (string) $row->order_id,
                        $row->owner_type === null ? null : (string) $row->owner_type,
                        $row->owner_id === null ? null : (string) $row->owner_id,
                        $ownerScoped,
                    );

                    if (! isset($matches[$key])) {
                        $completedWithoutMovement++;
                    }
                }
            });

        $reservations = InventoryOwnerScope::applyToLocationQuery(InventoryReservation::query());
        $reservationTotals = (clone $reservations)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? AND expires_at <= ? THEN 1 ELSE 0 END) as expired', [
                InventoryReservation::STATE_RESERVED,
                now(),
            ])
            ->toBase()
            ->first();

        return [
            'operations_total' => (int) $operationCounts->sum(),
            'operations_pending' => (int) $operationCounts->get(InventoryOperation::STATUS_PENDING, 0),
            'operations_failed' => (int) $operationCounts->get(InventoryOperation::STATUS_FAILED, 0),
            'completed_operations_without_movement' => $completedWithoutMovement,
            'reservations_total' => (int) ($reservationTotals->total ?? 0),
            'reservations_expired_pending_cleanup' => (int) ($reservationTotals->expired ?? 0),
            'reservations_without_allocations' => (clone $reservations)
                ->where('status', InventoryReservation::STATE_RESERVED)
                ->whereDoesntHave('allocations')
                ->count(),
            'terminal_reservations_with_allocations' => (clone $reservations)
                ->whereIn('status', [
                    InventoryReservation::STATE_COMMITTED,
                    InventoryReservation::STATE_RELEASED,
                    InventoryReservation::STATE_EXPIRED,
                ])
                ->whereHas('allocations')
                ->count(),
        ];
    }
    private function identity(string $reference, ?string $ownerType, ?string $ownerId, bool $ownerScoped): string
    {
        return $ownerScoped ? serialize([$reference, $ownerType, $ownerId]) : $reference;
    }
}
