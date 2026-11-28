<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Queries;

use AIArmada\Inventory\Features\Reservations\Api\ReservationReconciliation;

final class InventoryReconciliationReport
{
    /** @return array{operations_total: int, operations_pending: int, operations_failed: int, completed_operations_without_movement: int, reservations_total: int, reservations_expired_pending_cleanup: int, reservations_without_allocations: int, terminal_reservations_with_allocations: int} */
    public function getSummary(): array
    {
        return app(ReservationReconciliation::class)->getSummary();
    }
}
