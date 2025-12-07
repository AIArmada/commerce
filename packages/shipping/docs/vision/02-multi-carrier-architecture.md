# Shipping Vision: Multi-Carrier Architecture

> **Document:** 02 of 11  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

The multi-carrier architecture defines how `aiarmada/shipping` abstracts carrier-specific implementations behind a unified interface. This follows Laravel's **Manager Pattern** (similar to `CacheManager`, `FilesystemManager`) where drivers are resolved dynamically.

---

## Driver Interface Contract

### ShippingDriverInterface

```php
<?php

namespace AIArmada\Shipping\Contracts;

use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Data\TrackingData;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\LabelData;
use Illuminate\Support\Collection;

interface ShippingDriverInterface
{
    /**
     * Get unique carrier identifier.
     */
    public function getCarrierCode(): string;

    /**
     * Get human-readable carrier name.
     */
    public function getCarrierName(): string;

    /**
     * Check if carrier supports a specific capability.
     */
    public function supports(string $capability): bool;

    /**
     * Get available shipping methods for this carrier.
     *
     * @return Collection<int, ShippingMethodData>
     */
    public function getAvailableMethods(): Collection;

    /**
     * Get rate quotes for a shipment.
     *
     * @return Collection<int, RateQuoteData>
     */
    public function getRates(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): Collection;

    /**
     * Create a shipment with the carrier.
     */
    public function createShipment(ShipmentData $data): ShipmentResultData;

    /**
     * Cancel a shipment.
     */
    public function cancelShipment(string $trackingNumber): bool;

    /**
     * Generate shipping label.
     */
    public function generateLabel(string $trackingNumber, array $options = []): LabelData;

    /**
     * Track a shipment.
     */
    public function track(string $trackingNumber): TrackingData;

    /**
     * Validate an address.
     */
    public function validateAddress(AddressData $address): AddressValidationResult;

    /**
     * Check if carrier services this zone.
     */
    public function servicesZone(AddressData $destination): bool;
}
```

### Capability Constants

```php
<?php

namespace AIArmada\Shipping\Enums;

enum DriverCapability: string
{
    case RateQuotes = 'rate_quotes';
    case LabelGeneration = 'label_generation';
    case Tracking = 'tracking';
    case Webhooks = 'webhooks';
    case Returns = 'returns';
    case AddressValidation = 'address_validation';
    case PickupScheduling = 'pickup_scheduling';
    case CashOnDelivery = 'cash_on_delivery';
    case BatchOperations = 'batch_operations';
    case InsuranceClaims = 'insurance_claims';
}
```

---

## ShippingManager Implementation

### Manager Class Blueprint

```php
<?php

namespace AIArmada\Shipping;

use Illuminate\Support\Manager;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Drivers\ManualShippingDriver;
use AIArmada\Shipping\Drivers\NullShippingDriver;

class ShippingManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('shipping.default', 'manual');
    }

    /**
     * Create the null driver (for testing).
     */
    protected function createNullDriver(): ShippingDriverInterface
    {
        return new NullShippingDriver();
    }

    /**
     * Create the manual shipping driver.
     */
    protected function createManualDriver(): ShippingDriverInterface
    {
        return new ManualShippingDriver(
            $this->config->get('shipping.drivers.manual', [])
        );
    }

    /**
     * Get all registered drivers.
     *
     * @return array<string, ShippingDriverInterface>
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->customCreators) + 
               array_keys($this->config->get('shipping.drivers', []));
    }

    /**
     * Get all drivers that service a destination.
     *
     * @return Collection<int, ShippingDriverInterface>
     */
    public function getDriversForDestination(AddressData $destination): Collection
    {
        return collect($this->getAvailableDrivers())
            ->map(fn ($name) => $this->driver($name))
            ->filter(fn ($driver) => $driver->servicesZone($destination));
    }
}
```

---

## Driver Registration Patterns

### Self-Registration Pattern (Carrier Package)

```php
<?php

namespace AIArmada\Jnt;

use Illuminate\Support\ServiceProvider;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Jnt\Shipping\JntShippingDriver;
use AIArmada\Jnt\Services\JntExpressService;

class JntServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only register shipping driver if aiarmada/shipping is installed
        if (class_exists(ShippingManager::class)) {
            $this->registerShippingDriver();
        }
    }

    protected function registerShippingDriver(): void
    {
        $this->app->resolving(ShippingManager::class, function (ShippingManager $manager) {
            $manager->extend('jnt', function ($app) {
                return new JntShippingDriver(
                    $app->make(JntExpressService::class),
                    $app['config']->get('jnt', [])
                );
            });
        });
    }
}
```

### JntShippingDriver Blueprint

```php
<?php

namespace AIArmada\Jnt\Shipping;

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Services\JntTrackingService;
use AIArmada\Jnt\Builders\OrderBuilder;

class JntShippingDriver implements ShippingDriverInterface
{
    public function __construct(
        private readonly JntExpressService $service,
        private readonly JntTrackingService $trackingService,
        private readonly array $config
    ) {}

    public function getCarrierCode(): string
    {
        return 'jnt';
    }

    public function getCarrierName(): string
    {
        return 'J&T Express Malaysia';
    }

    public function supports(string $capability): bool
    {
        return match ($capability) {
            DriverCapability::LabelGeneration->value => true,
            DriverCapability::Tracking->value => true,
            DriverCapability::Webhooks->value => true,
            DriverCapability::CashOnDelivery->value => true,
            DriverCapability::BatchOperations->value => true,
            // J&T does NOT support rate quotes via API
            DriverCapability::RateQuotes->value => false,
            DriverCapability::Returns->value => false,
            DriverCapability::AddressValidation->value => false,
            default => false,
        };
    }

    public function getRates(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): Collection {
        // J&T doesn't have rate API - return configured flat rates
        return collect([
            new RateQuoteData(
                carrier: 'jnt',
                service: 'EZ',
                rate: $this->calculateFlatRate($packages, $destination),
                currency: 'MYR',
                estimatedDays: $this->getEstimatedDays($destination),
            ),
        ]);
    }

    public function createShipment(ShipmentData $data): ShipmentResultData
    {
        $builder = OrderBuilder::make()
            ->orderId($data->reference)
            ->sender($this->mapAddress($data->origin))
            ->receiver($this->mapAddress($data->destination));

        foreach ($data->items as $item) {
            $builder->addItem([
                'name' => $item->description,
                'quantity' => $item->quantity,
                'price' => $item->value,
            ]);
        }

        $result = $this->service->createOrder($builder->build());

        return new ShipmentResultData(
            success: $result->isSuccessful(),
            trackingNumber: $result->getTrackingNumber(),
            carrierReference: $result->getOrderId(),
            labelUrl: null, // Needs separate call
        );
    }

    public function track(string $trackingNumber): TrackingData
    {
        return $this->trackingService->track($trackingNumber);
    }

    // ... additional method implementations
}
```

---

## Configuration Structure

### shipping.php Config Blueprint

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Shipping Driver
    |--------------------------------------------------------------------------
    */
    'default' => env('SHIPPING_DRIVER', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Shipping Drivers
    |--------------------------------------------------------------------------
    |
    | Carrier packages auto-register via extend(). Manual config below for
    | built-in drivers or fallbacks.
    |
    */
    'drivers' => [
        'manual' => [
            'driver' => 'manual',
            'name' => 'Manual Shipping',
            'default_rate' => 1000, // RM10.00 in cents
            'free_shipping_threshold' => 15000, // RM150.00
        ],

        'flat_rate' => [
            'driver' => 'flat_rate',
            'name' => 'Flat Rate Shipping',
            'rates' => [
                'standard' => 800,  // RM8.00
                'express' => 1500,  // RM15.00
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Shopping Configuration
    |--------------------------------------------------------------------------
    */
    'rate_shopping' => [
        'enabled' => true,
        'strategy' => 'cheapest', // cheapest, fastest, preferred
        'preferred_carrier' => null,
        'cache_ttl' => 300, // 5 minutes
        'fallback_to_manual' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Carrier Priority (for rate shopping)
    |--------------------------------------------------------------------------
    */
    'carrier_priority' => [
        'jnt' => 1,
        'poslaju' => 2,
        'gdex' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Configuration
    |--------------------------------------------------------------------------
    */
    'tracking' => [
        'auto_sync' => true,
        'sync_interval' => 3600, // 1 hour
        'max_tracking_age' => 30, // days
        'webhook_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Label Generation
    |--------------------------------------------------------------------------
    */
    'labels' => [
        'format' => 'pdf', // pdf, png, zpl
        'size' => 'a6', // a4, a6, 4x6
        'storage_disk' => 'local',
        'storage_path' => 'shipping-labels',
    ],

    /*
    |--------------------------------------------------------------------------
    | Returns Configuration
    |--------------------------------------------------------------------------
    */
    'returns' => [
        'enabled' => true,
        'auto_approve' => false,
        'return_window_days' => 14,
        'generate_return_label' => true,
    ],
];
```

---

## Built-in Drivers

### ManualShippingDriver

For merchants who handle shipping outside the system:

```php
<?php

namespace AIArmada\Shipping\Drivers;

class ManualShippingDriver implements ShippingDriverInterface
{
    public function __construct(
        private readonly array $config
    ) {}

    public function getCarrierCode(): string
    {
        return 'manual';
    }

    public function getCarrierName(): string
    {
        return $this->config['name'] ?? 'Manual Shipping';
    }

    public function supports(string $capability): bool
    {
        // Manual driver supports nothing automatically
        return false;
    }

    public function getRates(...$args): Collection
    {
        return collect([
            new RateQuoteData(
                carrier: 'manual',
                service: 'Standard',
                rate: $this->config['default_rate'] ?? 0,
                currency: 'MYR',
                estimatedDays: $this->config['estimated_days'] ?? 3,
                calculatedLocally: true,
            ),
        ]);
    }

    public function createShipment(ShipmentData $data): ShipmentResultData
    {
        // Creates a local shipment record without external API
        return new ShipmentResultData(
            success: true,
            trackingNumber: 'MAN-' . strtoupper(uniqid()),
            requiresManualFulfillment: true,
        );
    }
}
```

### FlatRateShippingDriver

For simple flat-rate configurations:

```php
<?php

namespace AIArmada\Shipping\Drivers;

class FlatRateShippingDriver implements ShippingDriverInterface
{
    // Provides fixed rates based on configuration
    // Good for stores with simple shipping tiers
}
```

### TableRateShippingDriver

For zone/weight-based rate tables:

```php
<?php

namespace AIArmada\Shipping\Drivers;

class TableRateShippingDriver implements ShippingDriverInterface
{
    // Uses database rate tables
    // Zone × Weight × Service Level = Rate
}
```

---

## Driver Testing Support

### Null Driver for Tests

```php
<?php

namespace AIArmada\Shipping\Drivers;

class NullShippingDriver implements ShippingDriverInterface
{
    public function getCarrierCode(): string
    {
        return 'null';
    }

    public function supports(string $capability): bool
    {
        return true; // Everything "works"
    }

    public function getRates(...$args): Collection
    {
        return collect([
            new RateQuoteData(
                carrier: 'null',
                service: 'Test',
                rate: 0,
                estimatedDays: 1,
            ),
        ]);
    }

    public function createShipment(ShipmentData $data): ShipmentResultData
    {
        return new ShipmentResultData(
            success: true,
            trackingNumber: 'TEST-' . Str::random(10),
        );
    }

    // All other methods return successful no-ops
}
```

### Fake Driver for Testing

```php
<?php

namespace AIArmada\Shipping\Testing;

class FakeShippingDriver implements ShippingDriverInterface
{
    private Collection $shipments;
    private Collection $trackingData;

    public function setTrackingData(string $trackingNumber, TrackingData $data): self
    {
        $this->trackingData[$trackingNumber] = $data;
        return $this;
    }

    public function assertShipmentCreated(string $reference): void
    {
        PHPUnit::assertTrue(
            $this->shipments->contains('reference', $reference),
            "Shipment with reference [{$reference}] was not created."
        );
    }
}
```

---

## Integration Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     APPLICATION LAYER                            │
│  ┌─────────────┐    ┌──────────────┐    ┌──────────────────┐   │
│  │ Cart/Checkout│    │ Order System │    │ Admin Dashboard  │   │
│  └──────┬──────┘    └──────┬───────┘    └────────┬─────────┘   │
│         │                  │                      │              │
└─────────┼──────────────────┼──────────────────────┼──────────────┘
          │                  │                      │
          ▼                  ▼                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                    SHIPPING MANAGER                              │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  driver('jnt') ─────────► JntShippingDriver              │   │
│  │  driver('poslaju') ─────► PosLajuShippingDriver          │   │
│  │  driver('manual') ──────► ManualShippingDriver           │   │
│  │  driver('flat_rate') ───► FlatRateShippingDriver         │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │               ShippingDriverInterface                     │   │
│  │  • getRates()                                             │   │
│  │  • createShipment()                                       │   │
│  │  • track()                                                │   │
│  │  • generateLabel()                                        │   │
│  │  • cancelShipment()                                       │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
          │                  │                      │
          ▼                  ▼                      ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│   J&T Express   │  │    Pos Laju     │  │  Local Database │
│      API        │  │      API        │  │   (manual/flat) │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-rate-shopping-engine.md](03-rate-shopping-engine.md) - Multi-Carrier Rate Comparison

---

*This architecture ensures extensibility, testability, and consistency while allowing carriers to evolve independently from the core shipping logic.*
