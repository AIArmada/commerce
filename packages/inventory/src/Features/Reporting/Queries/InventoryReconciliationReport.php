<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Queries;

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\Inventory\Features\Reservations\Models\InventoryOperation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryReservation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;

final class InventoryReconciliationReport
{
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

        $operation = $operations->getModel();
        $movement = new InventoryMovement;
        $connection = $operation->getConnection();
        $grammar = $connection->getQueryGrammar();
        $referenceColumn = $grammar->wrap($movement->qualifyColumn('reference'));
        $orderColumn = $grammar->wrap($operation->qualifyColumn('order_id'));
        $cast = match (ConnectionDriver::name($connection)) {
            'mysql', 'mariadb' => 'CHAR',
            'sqlsrv' => 'VARCHAR(36)',
            default => 'TEXT',
        };

        $matchingMovement = InventoryOwnerScope::applyToLocationQuery(InventoryMovement::query())
            ->selectRaw('1')
            ->whereRaw("{$referenceColumn} = CAST({$orderColumn} AS {$cast})");

        if (InventoryOwnerScope::isEnabled()) {
            $matchingMovement->where(function (Builder $query) use ($operation, $movement): void {
                $query->where(function (Builder $owned) use ($operation, $movement): void {
                    $owned->whereColumn($movement->qualifyColumn('owner_type'), $operation->qualifyColumn('owner_type'))
                        ->whereColumn($movement->qualifyColumn('owner_id'), $operation->qualifyColumn('owner_id'));
                })->orWhere(function (Builder $global) use ($operation, $movement): void {
                    $global->whereNull($movement->qualifyColumn('owner_type'))
                        ->whereNull($movement->qualifyColumn('owner_id'))
                        ->whereNull($operation->qualifyColumn('owner_type'))
                        ->whereNull($operation->qualifyColumn('owner_id'));
                });
            });
        }

        $completedWithoutMovement = (clone $operations)
            ->where('status', InventoryOperation::STATUS_COMPLETED)
            ->whereNotExists($matchingMovement->toBase())
            ->count();

        $reservations = InventoryOwnerScope::applyToLocationQuery(InventoryReservation::query());

        return [
            'operations_total' => (int) $operationCounts->sum(),
            'operations_pending' => (int) $operationCounts->get(InventoryOperation::STATUS_PENDING, 0),
            'operations_failed' => (int) $operationCounts->get(InventoryOperation::STATUS_FAILED, 0),
            'completed_operations_without_movement' => $completedWithoutMovement,
            'reservations_total' => (clone $reservations)->count(),
            'reservations_expired_pending_cleanup' => (clone $reservations)
                ->where('status', InventoryReservation::STATE_RESERVED)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->count(),
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
}
