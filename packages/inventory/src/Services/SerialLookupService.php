<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventorySerial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class SerialLookupService
{
    /**
     * Find serial by exact serial number.
     */
    public function findBySerialNumber(string $serialNumber): ?InventorySerial
    {
        return InventorySerial::where('serial_number', $serialNumber)->first();
    }

    /**
     * Find serial by serial number or fail.
     */
    public function findBySerialNumberOrFail(string $serialNumber): InventorySerial
    {
        return InventorySerial::where('serial_number', $serialNumber)->firstOrFail();
    }

    /**
     * Search serials by partial serial number.
     *
     * @return Collection<int, InventorySerial>
     */
    public function searchBySerialNumber(string $partialSerialNumber, int $limit = 25): Collection
    {
        return InventorySerial::query()
            ->where('serial_number', 'like', "%{$partialSerialNumber}%")
            ->orderBy('serial_number')
            ->limit($limit)
            ->get();
    }

    /**
     * Find serial by order ID.
     */
    public function findByOrderId(string $orderId): ?InventorySerial
    {
        return InventorySerial::where('order_id', $orderId)->first();
    }

    /**
     * Get all serials by order ID.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getAllByOrderId(string $orderId): Collection
    {
        return InventorySerial::where('order_id', $orderId)->get();
    }

    /**
     * Find serial by customer ID.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getByCustomerId(string $customerId): Collection
    {
        return InventorySerial::where('customer_id', $customerId)
            ->orderBy('sold_at', 'desc')
            ->get();
    }

    /**
     * Get serials for an inventoryable model.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getForModel(Model $model): Collection
    {
        return InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get serials at a location.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getAtLocation(string $locationId): Collection
    {
        return InventorySerial::query()
            ->atLocation($locationId)
            ->orderBy('serial_number')
            ->get();
    }

    /**
     * Get serials by batch.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getByBatch(string $batchId): Collection
    {
        return InventorySerial::where('batch_id', $batchId)
            ->orderBy('serial_number')
            ->get();
    }

    /**
     * Get serials by status.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getByStatus(SerialStatus $status): Collection
    {
        return InventorySerial::where('status', $status->value)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get serials by condition.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getByCondition(SerialCondition $condition): Collection
    {
        return InventorySerial::where('condition', $condition->value)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get available serials for sale.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getAvailableForSale(Model $model, ?string $locationId = null): Collection
    {
        $query = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->sellable();

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        return $query->get();
    }

    /**
     * Get serials with expiring warranty.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getExpiringWarranty(int $daysAhead = 30): Collection
    {
        return InventorySerial::query()
            ->whereNotNull('warranty_expires_at')
            ->where('warranty_expires_at', '>', now())
            ->where('warranty_expires_at', '<=', now()->addDays($daysAhead))
            ->orderBy('warranty_expires_at')
            ->get();
    }

    /**
     * Get serials under warranty for a customer.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getCustomerWarrantyItems(string $customerId): Collection
    {
        return InventorySerial::query()
            ->where('customer_id', $customerId)
            ->where('status', SerialStatus::Sold->value)
            ->whereNotNull('warranty_expires_at')
            ->where('warranty_expires_at', '>', now())
            ->orderBy('warranty_expires_at')
            ->get();
    }

    /**
     * Advanced search with multiple criteria.
     *
     * @param  array<string, mixed>  $criteria
     * @return LengthAwarePaginator<InventorySerial>
     */
    public function search(array $criteria, int $perPage = 25): LengthAwarePaginator
    {
        $query = InventorySerial::query();

        $this->applyCriteria($query, $criteria);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Count serials by status for a model.
     *
     * @return array<string, int>
     */
    public function countByStatus(Model $model): array
    {
        $counts = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $result = [];
        foreach (SerialStatus::cases() as $status) {
            $result[$status->value] = $counts[$status->value] ?? 0;
        }

        return $result;
    }

    /**
     * Count serials by condition for a model.
     *
     * @return array<string, int>
     */
    public function countByCondition(Model $model): array
    {
        $counts = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->selectRaw('`condition`, count(*) as count')
            ->groupBy('condition')
            ->pluck('count', 'condition')
            ->toArray();

        $result = [];
        foreach (SerialCondition::cases() as $condition) {
            $result[$condition->value] = $counts[$condition->value] ?? 0;
        }

        return $result;
    }

    /**
     * Get total value of serials at a location.
     */
    public function getTotalValue(string $locationId): int
    {
        return (int) InventorySerial::query()
            ->atLocation($locationId)
            ->sum('unit_cost_minor');
    }

    /**
     * Get total value of serials for a model.
     */
    public function getTotalValueForModel(Model $model, ?string $status = null): int
    {
        $query = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());

        if ($status !== null) {
            $query->where('status', $status);
        }

        return (int) $query->sum('unit_cost_minor');
    }

    /**
     * Check if serial number exists.
     */
    public function serialNumberExists(string $serialNumber): bool
    {
        return InventorySerial::where('serial_number', $serialNumber)->exists();
    }

    /**
     * Validate if serial numbers are available.
     *
     * @param  array<int, string>  $serialNumbers
     * @return array<string, bool>
     */
    public function validateSerialNumbers(array $serialNumbers): array
    {
        $existing = InventorySerial::whereIn('serial_number', $serialNumbers)
            ->pluck('serial_number')
            ->toArray();

        $result = [];
        foreach ($serialNumbers as $serialNumber) {
            $result[$serialNumber] = ! in_array($serialNumber, $existing, true);
        }

        return $result;
    }

    /**
     * Apply search criteria to query.
     *
     * @param  Builder<InventorySerial>  $query
     * @param  array<string, mixed>  $criteria
     */
    private function applyCriteria(Builder $query, array $criteria): void
    {
        if (isset($criteria['serial_number'])) {
            $query->where('serial_number', 'like', "%{$criteria['serial_number']}%");
        }

        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $statuses = array_map(fn ($s) => $s instanceof SerialStatus ? $s->value : $s, $criteria['status']);
                $query->whereIn('status', $statuses);
            } else {
                $status = $criteria['status'] instanceof SerialStatus ? $criteria['status']->value : $criteria['status'];
                $query->where('status', $status);
            }
        }

        if (isset($criteria['condition'])) {
            if (is_array($criteria['condition'])) {
                $conditions = array_map(fn ($c) => $c instanceof SerialCondition ? $c->value : $c, $criteria['condition']);
                $query->whereIn('condition', $conditions);
            } else {
                $condition = $criteria['condition'] instanceof SerialCondition ? $criteria['condition']->value : $criteria['condition'];
                $query->where('condition', $condition);
            }
        }

        if (isset($criteria['location_id'])) {
            $query->where('location_id', $criteria['location_id']);
        }

        if (isset($criteria['batch_id'])) {
            $query->where('batch_id', $criteria['batch_id']);
        }

        if (isset($criteria['order_id'])) {
            $query->where('order_id', $criteria['order_id']);
        }

        if (isset($criteria['customer_id'])) {
            $query->where('customer_id', $criteria['customer_id']);
        }

        if (isset($criteria['inventoryable_type']) && isset($criteria['inventoryable_id'])) {
            $query->where('inventoryable_type', $criteria['inventoryable_type'])
                ->where('inventoryable_id', $criteria['inventoryable_id']);
        }

        if (isset($criteria['received_from'])) {
            $query->where('received_at', '>=', $criteria['received_from']);
        }

        if (isset($criteria['received_to'])) {
            $query->where('received_at', '<=', $criteria['received_to']);
        }

        if (isset($criteria['sold_from'])) {
            $query->where('sold_at', '>=', $criteria['sold_from']);
        }

        if (isset($criteria['sold_to'])) {
            $query->where('sold_at', '<=', $criteria['sold_to']);
        }

        if (isset($criteria['warranty_expires_before'])) {
            $query->where('warranty_expires_at', '<=', $criteria['warranty_expires_before']);
        }

        if (isset($criteria['warranty_expires_after'])) {
            $query->where('warranty_expires_at', '>=', $criteria['warranty_expires_after']);
        }

        if (isset($criteria['has_warranty']) && $criteria['has_warranty']) {
            $query->whereNotNull('warranty_expires_at');
        }

        if (isset($criteria['under_warranty']) && $criteria['under_warranty']) {
            $query->whereNotNull('warranty_expires_at')
                ->where('warranty_expires_at', '>', now());
        }

        if (isset($criteria['min_cost'])) {
            $query->where('unit_cost_minor', '>=', $criteria['min_cost']);
        }

        if (isset($criteria['max_cost'])) {
            $query->where('unit_cost_minor', '<=', $criteria['max_cost']);
        }
    }
}
