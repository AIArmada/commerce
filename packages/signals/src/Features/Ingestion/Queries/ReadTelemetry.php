<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Ingestion\Queries;

use AIArmada\Signals\Features\Ingestion\Api\TelemetryRead;
use AIArmada\Signals\Features\Ingestion\Models\SignalIdentity;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use AIArmada\Signals\Features\Ingestion\Support\SignalOwnershipGuard;
use AIArmada\Signals\Support\SignalCurrency;
use Carbon\CarbonImmutable;

final readonly class ReadTelemetry implements TelemetryRead
{
    public function propertyOptions(): array
    {
        return TrackedProperty::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function propertyCurrency(string $id): ?string
    {
        $property = TrackedProperty::query()->forOwner()->whereKey($id)->first(['currency']);

        return $property === null ? null : SignalCurrency::code($property->currency);
    }

    public function requirePropertyCurrency(string $id): string
    {
        $property = TrackedProperty::query()->forOwner()->whereKey($id)->firstOrFail(['currency']);

        return SignalCurrency::code($property->currency);
    }

    public function propertyCurrencyForWrite(string $id): string
    {
        $property = SignalOwnershipGuard::ownerScopingEnabled()
            ? SignalOwnershipGuard::findPropertyOrFail($id, SignalOwnershipGuard::resolveOwnerOrFail('write signal property telemetry'))
            : SignalOwnershipGuard::findPropertyUnscopedOrFail($id);

        return SignalCurrency::code($property->currency);
    }

    public function counts(?string $propertyId, CarbonImmutable $startAt, CarbonImmutable $endAt): array
    {
        $properties = TrackedProperty::query();
        $identities = SignalIdentity::query()->whereBetween('last_seen_at', [$startAt, $endAt]);

        if ($propertyId !== null) {
            $properties->whereKey($propertyId);
            $identities->where('tracked_property_id', $propertyId);
        }

        return [
            'tracked_properties' => $properties->count(),
            'identities' => $identities->count(),
        ];
    }
}
