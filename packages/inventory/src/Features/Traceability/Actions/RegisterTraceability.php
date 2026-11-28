<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use AIArmada\Inventory\Features\Traceability\Persistence\BatchService;
use AIArmada\Inventory\Features\Traceability\Persistence\SerialService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RegisterTraceability
{
    public function __construct(
        private BatchService $batches,
        private SerialService $serials,
        private UpdateTraceabilityDetails $details,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function batch(string $levelId, array $attributes): InventoryBatch
    {
        return DB::transaction(function () use ($levelId, $attributes): InventoryBatch {
            $level = $this->level($levelId);
            $quantity = filter_var($attributes['quantity_received'] ?? null, FILTER_VALIDATE_INT);

            if ($quantity === false || $quantity <= 0) {
                throw new InvalidArgumentException('Quantity must be a positive integer');
            }

            $batch = $this->batches->createBatch(
                model: $this->item($level),
                batchNumber: $this->identifier($attributes, 'batch_number'),
                locationId: $level->location_id,
                quantity: $quantity,
                expiresAt: $this->date($attributes, 'expires_at'),
                manufacturedAt: $this->date($attributes, 'manufactured_at'),
                lotNumber: isset($attributes['lot_number']) ? (string) $attributes['lot_number'] : null,
                unitCostMinor: $this->cost($attributes),
            );

            return $this->details->batch($batch, array_intersect_key($attributes, array_flip(['supplier_batch_number', 'received_at', 'metadata'])));
        });
    }

    /** @param array<string, mixed> $attributes */
    public function serial(string $levelId, array $attributes): InventorySerial
    {
        return DB::transaction(function () use ($levelId, $attributes): InventorySerial {
            $level = $this->level($levelId);
            $batchId = isset($attributes['batch_id']) ? (string) $attributes['batch_id'] : null;

            $serial = $this->serials->register(
                model: $this->item($level),
                serialNumber: $this->identifier($attributes, 'serial_number'),
                locationId: $level->location_id,
                batchId: $batchId,
                condition: SerialCondition::from((string) ($attributes['condition'] ?? SerialCondition::New->value)),
                unitCostMinor: $this->cost($attributes),
                warrantyExpiresAt: $this->date($attributes, 'warranty_expires_at'),
            );

            return $this->details->serial($serial, array_intersect_key($attributes, array_flip(['received_at'])));
        });
    }

    private function level(string $id): InventoryLevel
    {
        $query = InventoryLevel::query();

        if (InventoryOwnerScope::isEnabled()) {
            $owner = InventoryOwnerScope::resolveOwner();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            $query->forOwner($owner, false);
        }

        return $query->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function item(InventoryLevel $level): Model
    {
        $item = $level->inventoryable;

        if (! $item instanceof Model) {
            throw new InvalidArgumentException('Inventory item no longer exists');
        }

        return $item;
    }

    /** @param array<string, mixed> $attributes */
    private function identifier(array $attributes, string $key): string
    {
        $value = $attributes[$key] ?? null;

        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException('Traceability identifier is required');
        }

        return mb_trim($value);
    }

    /** @param array<string, mixed> $attributes */
    private function date(array $attributes, string $key): ?CarbonImmutable
    {
        return isset($attributes[$key]) ? CarbonImmutable::parse((string) $attributes[$key]) : null;
    }

    /** @param array<string, mixed> $attributes */
    private function cost(array $attributes): ?int
    {
        if (! isset($attributes['unit_cost_minor'])) {
            return null;
        }

        $cost = filter_var($attributes['unit_cost_minor'], FILTER_VALIDATE_INT);

        if ($cost === false || $cost < 0) {
            throw new InvalidArgumentException('Unit cost must be a nonnegative integer');
        }

        return $cost;
    }
}
