<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Data;

final readonly class AllocationContext
{
    public function __construct(
        public ?string $locationId = null,
        public ?string $orderId = null,
        public ?string $customerId = null,
        public bool $excludeExpiringSoon = false,
        public int $minDaysToExpiry = 7,
        public bool $allowPartialAllocation = true,
        public bool $createBackorderForShortfall = false,
        public ?float $originX = null,
        public ?float $originY = null,
        public ?float $originZ = null,
        public ?int $maxLocations = null,
        public bool $preferSameLocation = true,
        public bool $preferWholeBatches = false,
        public ?string $preferredZone = null,
        public ?string $excludeZone = null,
        /** @var array<string>|null */
        public ?array $excludeLocationIds = null,
        /** @var array<string>|null */
        public ?array $preferLocationIds = null,
    ) {}

    public static function forOrder(string $orderId, ?string $customerId = null): self
    {
        return new self(
            orderId: $orderId,
            customerId: $customerId,
            createBackorderForShortfall: true,
        );
    }

    public static function forLocation(string $locationId): self
    {
        return new self(locationId: $locationId);
    }

    public static function forPerishables(int $minDaysToExpiry = 7): self
    {
        return new self(
            excludeExpiringSoon: true,
            minDaysToExpiry: $minDaysToExpiry,
        );
    }

    public static function fromCoordinates(float $x, float $y, ?float $z = null): self
    {
        return new self(
            originX: $x,
            originY: $y,
            originZ: $z,
        );
    }

    public function withLocation(string $locationId): self
    {
        return new self(
            locationId: $locationId,
            orderId: $this->orderId,
            customerId: $this->customerId,
            excludeExpiringSoon: $this->excludeExpiringSoon,
            minDaysToExpiry: $this->minDaysToExpiry,
            allowPartialAllocation: $this->allowPartialAllocation,
            createBackorderForShortfall: $this->createBackorderForShortfall,
            originX: $this->originX,
            originY: $this->originY,
            originZ: $this->originZ,
            maxLocations: $this->maxLocations,
            preferSameLocation: $this->preferSameLocation,
            preferWholeBatches: $this->preferWholeBatches,
            preferredZone: $this->preferredZone,
            excludeZone: $this->excludeZone,
            excludeLocationIds: $this->excludeLocationIds,
            preferLocationIds: $this->preferLocationIds,
        );
    }

    public function withOrder(string $orderId): self
    {
        return new self(
            locationId: $this->locationId,
            orderId: $orderId,
            customerId: $this->customerId,
            excludeExpiringSoon: $this->excludeExpiringSoon,
            minDaysToExpiry: $this->minDaysToExpiry,
            allowPartialAllocation: $this->allowPartialAllocation,
            createBackorderForShortfall: $this->createBackorderForShortfall,
            originX: $this->originX,
            originY: $this->originY,
            originZ: $this->originZ,
            maxLocations: $this->maxLocations,
            preferSameLocation: $this->preferSameLocation,
            preferWholeBatches: $this->preferWholeBatches,
            preferredZone: $this->preferredZone,
            excludeZone: $this->excludeZone,
            excludeLocationIds: $this->excludeLocationIds,
            preferLocationIds: $this->preferLocationIds,
        );
    }

    public function withBackorderSupport(): self
    {
        return new self(
            locationId: $this->locationId,
            orderId: $this->orderId,
            customerId: $this->customerId,
            excludeExpiringSoon: $this->excludeExpiringSoon,
            minDaysToExpiry: $this->minDaysToExpiry,
            allowPartialAllocation: $this->allowPartialAllocation,
            createBackorderForShortfall: true,
            originX: $this->originX,
            originY: $this->originY,
            originZ: $this->originZ,
            maxLocations: $this->maxLocations,
            preferSameLocation: $this->preferSameLocation,
            preferWholeBatches: $this->preferWholeBatches,
            preferredZone: $this->preferredZone,
            excludeZone: $this->excludeZone,
            excludeLocationIds: $this->excludeLocationIds,
            preferLocationIds: $this->preferLocationIds,
        );
    }

    public function withExpiryConstraint(int $minDays = 7): self
    {
        return new self(
            locationId: $this->locationId,
            orderId: $this->orderId,
            customerId: $this->customerId,
            excludeExpiringSoon: true,
            minDaysToExpiry: $minDays,
            allowPartialAllocation: $this->allowPartialAllocation,
            createBackorderForShortfall: $this->createBackorderForShortfall,
            originX: $this->originX,
            originY: $this->originY,
            originZ: $this->originZ,
            maxLocations: $this->maxLocations,
            preferSameLocation: $this->preferSameLocation,
            preferWholeBatches: $this->preferWholeBatches,
            preferredZone: $this->preferredZone,
            excludeZone: $this->excludeZone,
            excludeLocationIds: $this->excludeLocationIds,
            preferLocationIds: $this->preferLocationIds,
        );
    }

    public function withMaxLocations(int $max): self
    {
        return new self(
            locationId: $this->locationId,
            orderId: $this->orderId,
            customerId: $this->customerId,
            excludeExpiringSoon: $this->excludeExpiringSoon,
            minDaysToExpiry: $this->minDaysToExpiry,
            allowPartialAllocation: $this->allowPartialAllocation,
            createBackorderForShortfall: $this->createBackorderForShortfall,
            originX: $this->originX,
            originY: $this->originY,
            originZ: $this->originZ,
            maxLocations: $max,
            preferSameLocation: $this->preferSameLocation,
            preferWholeBatches: $this->preferWholeBatches,
            preferredZone: $this->preferredZone,
            excludeZone: $this->excludeZone,
            excludeLocationIds: $this->excludeLocationIds,
            preferLocationIds: $this->preferLocationIds,
        );
    }

    public function withPreferredZone(string $zone): self
    {
        return new self(
            locationId: $this->locationId,
            orderId: $this->orderId,
            customerId: $this->customerId,
            excludeExpiringSoon: $this->excludeExpiringSoon,
            minDaysToExpiry: $this->minDaysToExpiry,
            allowPartialAllocation: $this->allowPartialAllocation,
            createBackorderForShortfall: $this->createBackorderForShortfall,
            originX: $this->originX,
            originY: $this->originY,
            originZ: $this->originZ,
            maxLocations: $this->maxLocations,
            preferSameLocation: $this->preferSameLocation,
            preferWholeBatches: $this->preferWholeBatches,
            preferredZone: $zone,
            excludeZone: $this->excludeZone,
            excludeLocationIds: $this->excludeLocationIds,
            preferLocationIds: $this->preferLocationIds,
        );
    }

    public function excludingZone(string $zone): self
    {
        return new self(
            locationId: $this->locationId,
            orderId: $this->orderId,
            customerId: $this->customerId,
            excludeExpiringSoon: $this->excludeExpiringSoon,
            minDaysToExpiry: $this->minDaysToExpiry,
            allowPartialAllocation: $this->allowPartialAllocation,
            createBackorderForShortfall: $this->createBackorderForShortfall,
            originX: $this->originX,
            originY: $this->originY,
            originZ: $this->originZ,
            maxLocations: $this->maxLocations,
            preferSameLocation: $this->preferSameLocation,
            preferWholeBatches: $this->preferWholeBatches,
            preferredZone: $this->preferredZone,
            excludeZone: $zone,
            excludeLocationIds: $this->excludeLocationIds,
            preferLocationIds: $this->preferLocationIds,
        );
    }

    /**
     * @param  array<string>  $locationIds
     */
    public function excludingLocations(array $locationIds): self
    {
        return new self(
            locationId: $this->locationId,
            orderId: $this->orderId,
            customerId: $this->customerId,
            excludeExpiringSoon: $this->excludeExpiringSoon,
            minDaysToExpiry: $this->minDaysToExpiry,
            allowPartialAllocation: $this->allowPartialAllocation,
            createBackorderForShortfall: $this->createBackorderForShortfall,
            originX: $this->originX,
            originY: $this->originY,
            originZ: $this->originZ,
            maxLocations: $this->maxLocations,
            preferSameLocation: $this->preferSameLocation,
            preferWholeBatches: $this->preferWholeBatches,
            preferredZone: $this->preferredZone,
            excludeZone: $this->excludeZone,
            excludeLocationIds: $locationIds,
            preferLocationIds: $this->preferLocationIds,
        );
    }

    /**
     * @param  array<string>  $locationIds
     */
    public function preferringLocations(array $locationIds): self
    {
        return new self(
            locationId: $this->locationId,
            orderId: $this->orderId,
            customerId: $this->customerId,
            excludeExpiringSoon: $this->excludeExpiringSoon,
            minDaysToExpiry: $this->minDaysToExpiry,
            allowPartialAllocation: $this->allowPartialAllocation,
            createBackorderForShortfall: $this->createBackorderForShortfall,
            originX: $this->originX,
            originY: $this->originY,
            originZ: $this->originZ,
            maxLocations: $this->maxLocations,
            preferSameLocation: $this->preferSameLocation,
            preferWholeBatches: $this->preferWholeBatches,
            preferredZone: $this->preferredZone,
            excludeZone: $this->excludeZone,
            excludeLocationIds: $this->excludeLocationIds,
            preferLocationIds: $locationIds,
        );
    }

    public function hasOriginCoordinates(): bool
    {
        return $this->originX !== null && $this->originY !== null;
    }
}
