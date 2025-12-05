# Multi-Carrier Abstraction Layer

> **Document:** 2 of 9  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision

---

## Overview

Transform the single-carrier JNT package into a **unified shipping platform** supporting multiple carriers through a common abstraction layer.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                       APPLICATION LAYER                          │
│   ShippingManager::carrier('jnt')->createShipment($data)        │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                     ABSTRACTION LAYER                            │
│                                                                  │
│  ┌────────────────┐  ┌────────────────┐  ┌───────────────────┐  │
│  │ CarrierManager │  │ RateCalculator │  │ AddressValidator  │  │
│  │ (Registry)     │  │ (Comparison)   │  │ (Standardization) │  │
│  └───────┬────────┘  └───────┬────────┘  └────────┬──────────┘  │
│          │                   │                    │              │
│          ▼                   ▼                    ▼              │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    CarrierContract                          ││
│  │   createShipment() | track() | cancel() | getLabel()       ││
│  │   getRates() | validateAddress() | schedulePickup()         ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
└────────────────────────────┬────────────────────────────────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
        ▼                    ▼                    ▼
┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│ JntCarrier    │  │ PosLajuCarrier│  │ DhlCarrier    │
│ (Adapter)     │  │ (Adapter)     │  │ (Adapter)     │
├───────────────┤  ├───────────────┤  ├───────────────┤
│ - apiClient   │  │ - apiClient   │  │ - apiClient   │
│ - credentials │  │ - credentials │  │ - credentials │
│ - mappers     │  │ - mappers     │  │ - mappers     │
└───────────────┘  └───────────────┘  └───────────────┘
```

---

## Carrier Contract

### CarrierContract Interface

```php
interface CarrierContract
{
    /**
     * Get the carrier identifier.
     */
    public function getIdentifier(): string;

    /**
     * Get the carrier display name.
     */
    public function getName(): string;

    /**
     * Get available service types for this carrier.
     *
     * @return array<string, string>
     */
    public function getServiceTypes(): array;

    /**
     * Check if the carrier is available/configured.
     */
    public function isAvailable(): bool;

    /**
     * Create a shipment.
     */
    public function createShipment(ShipmentData $data): ShipmentResult;

    /**
     * Get tracking information.
     */
    public function track(string $trackingNumber): TrackingResult;

    /**
     * Cancel a shipment.
     */
    public function cancel(string $shipmentId, ?string $reason = null): CancellationResult;

    /**
     * Get shipping label.
     */
    public function getLabel(string $shipmentId, LabelFormat $format = LabelFormat::Pdf): LabelResult;

    /**
     * Get shipping rates.
     */
    public function getRates(RateRequest $request): RateCollection;

    /**
     * Validate an address.
     */
    public function validateAddress(AddressData $address): AddressValidationResult;

    /**
     * Schedule a pickup.
     */
    public function schedulePickup(PickupRequest $request): PickupResult;

    /**
     * Get carrier capabilities.
     */
    public function getCapabilities(): CarrierCapabilities;
}
```

---

## Carrier Capabilities

### CarrierCapabilities Value Object

```php
final readonly class CarrierCapabilities
{
    public function __construct(
        public bool $supportsRateQuotes = false,
        public bool $supportsAddressValidation = false,
        public bool $supportsPickupScheduling = false,
        public bool $supportsReturns = false,
        public bool $supportsCod = false,
        public bool $supportsInsurance = false,
        public bool $supportsSignatureRequired = false,
        public bool $supportsDimensionalWeight = false,
        public bool $supportsWebhooks = false,
        public bool $supportsBatchOperations = false,
        public array $supportedLabelFormats = [LabelFormat::Pdf],
        public int $maxPackageWeight = 30000, // grams
        public int $maxCodValue = 500000, // cents
    ) {}
}
```

---

## Shipping Manager

### ShippingManager Service

```php
final class ShippingManager
{
    /**
     * @var array<string, CarrierContract>
     */
    private array $carriers = [];

    private ?string $defaultCarrier = null;

    public function __construct(
        private readonly CarrierFactory $factory,
        private readonly EventDispatcher $events,
    ) {}

    /**
     * Register a carrier.
     */
    public function extend(string $identifier, CarrierContract $carrier): self
    {
        $this->carriers[$identifier] = $carrier;
        return $this;
    }

    /**
     * Get a carrier instance.
     */
    public function carrier(?string $identifier = null): CarrierContract
    {
        $identifier ??= $this->defaultCarrier ?? throw new NoDefaultCarrierException();

        return $this->carriers[$identifier]
            ?? throw new CarrierNotFoundException($identifier);
    }

    /**
     * Get all registered carriers.
     *
     * @return array<string, CarrierContract>
     */
    public function carriers(): array
    {
        return $this->carriers;
    }

    /**
     * Get available carriers (configured and ready).
     *
     * @return array<string, CarrierContract>
     */
    public function available(): array
    {
        return array_filter(
            $this->carriers,
            fn (CarrierContract $carrier) => $carrier->isAvailable()
        );
    }

    /**
     * Create a shipment using the specified or default carrier.
     */
    public function createShipment(ShipmentData $data, ?string $carrier = null): ShipmentResult
    {
        $result = $this->carrier($carrier)->createShipment($data);
        
        $this->events->dispatch(new ShipmentCreated($result));
        
        return $result;
    }

    /**
     * Get rates from multiple carriers.
     */
    public function compareRates(RateRequest $request, ?array $carriers = null): RateComparisonResult
    {
        $carriers ??= array_keys($this->available());
        $rates = [];

        foreach ($carriers as $identifier) {
            try {
                $carrierRates = $this->carrier($identifier)->getRates($request);
                $rates[$identifier] = $carrierRates;
            } catch (RatesUnavailableException $e) {
                // Skip carriers that can't quote
            }
        }

        return new RateComparisonResult($rates, $request);
    }

    /**
     * Auto-select carrier based on rules.
     */
    public function selectCarrier(ShipmentData $data): CarrierSelection
    {
        return app(CarrierSelectionEngine::class)->select($data, $this->available());
    }
}
```

---

## Data Transfer Objects

### Unified DTOs

```php
final readonly class ShipmentData
{
    public function __construct(
        public AddressData $sender,
        public AddressData $recipient,
        public PackageData $package,
        public ?MoneyData $codAmount = null,
        public ?MoneyData $insuranceValue = null,
        public ?string $serviceType = null,
        public ?string $reference = null,
        public ?DateTimeInterface $pickupDate = null,
        public array $items = [],
        public array $metadata = [],
    ) {}
}

final readonly class AddressData
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $addressLine1,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public string $postcode,
        public string $state,
        public string $country = 'MY',
        public ?string $email = null,
        public ?string $company = null,
    ) {}
}

final readonly class PackageData
{
    public function __construct(
        public int $weightGrams,
        public ?int $lengthCm = null,
        public ?int $widthCm = null,
        public ?int $heightCm = null,
        public ?string $contents = null,
        public int $quantity = 1,
    ) {}

    public function volumetricWeight(int $divisor = 5000): int
    {
        if (! $this->lengthCm || ! $this->widthCm || ! $this->heightCm) {
            return $this->weightGrams;
        }

        $volumetric = ($this->lengthCm * $this->widthCm * $this->heightCm) / $divisor * 1000;
        
        return max($this->weightGrams, (int) $volumetric);
    }
}
```

---

## Carrier Adapter Pattern

### J&T Carrier Implementation

```php
final class JntCarrier implements CarrierContract
{
    public function __construct(
        private readonly JntApiClient $client,
        private readonly JntMapper $mapper,
        private readonly JntConfig $config,
    ) {}

    public function getIdentifier(): string
    {
        return 'jnt';
    }

    public function getName(): string
    {
        return 'J&T Express';
    }

    public function getServiceTypes(): array
    {
        return [
            'EZ' => 'Domestic Standard',
            'EX' => 'Express Next Day',
            'FD' => 'Fresh Delivery',
            'DO' => 'Door to Door',
            'JS' => 'Same Day',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->config->isConfigured();
    }

    public function createShipment(ShipmentData $data): ShipmentResult
    {
        $jntOrder = $this->mapper->toJntOrder($data);
        $response = $this->client->createOrder($jntOrder);
        
        return $this->mapper->toShipmentResult($response);
    }

    public function track(string $trackingNumber): TrackingResult
    {
        $response = $this->client->trackParcel($trackingNumber);
        
        return $this->mapper->toTrackingResult($response);
    }

    public function getRates(RateRequest $request): RateCollection
    {
        // J&T doesn't have a rate API, use cached rate cards
        return $this->rateCalculator->calculate($request);
    }

    public function getCapabilities(): CarrierCapabilities
    {
        return new CarrierCapabilities(
            supportsRateQuotes: false, // Rate cards only
            supportsAddressValidation: false,
            supportsPickupScheduling: true,
            supportsReturns: false,
            supportsCod: true,
            supportsInsurance: true,
            supportsSignatureRequired: false,
            supportsDimensionalWeight: true,
            supportsWebhooks: true,
            supportsBatchOperations: true,
            supportedLabelFormats: [LabelFormat::Pdf],
            maxPackageWeight: 30000,
            maxCodValue: 500000,
        );
    }
}
```

---

## Supported Carriers (Vision)

| Carrier | Identifier | Priority | Notes |
|---------|------------|----------|-------|
| J&T Express | `jnt` | 1 | Already implemented |
| Pos Laju | `poslaju` | 2 | National postal carrier |
| DHL eCommerce | `dhl` | 3 | International + domestic |
| GDex | `gdex` | 4 | Popular domestic |
| Ninja Van | `ninjavan` | 5 | Regional player |
| City-Link | `citylink` | 6 | B2B strength |
| ABX Express | `abx` | 7 | Economy option |
| SF Express | `sf` | 8 | Premium international |

---

## Configuration

```php
// config/shipping.php
return [
    'default' => env('SHIPPING_DEFAULT_CARRIER', 'jnt'),

    'carriers' => [
        'jnt' => [
            'driver' => 'jnt',
            'credentials' => [
                'customer_code' => env('JNT_CUSTOMER_CODE'),
                'api_key' => env('JNT_API_KEY'),
                'api_account' => env('JNT_API_ACCOUNT'),
            ],
            'sandbox' => env('JNT_SANDBOX', false),
        ],

        'poslaju' => [
            'driver' => 'poslaju',
            'credentials' => [
                'api_key' => env('POSLAJU_API_KEY'),
                'secret_key' => env('POSLAJU_SECRET_KEY'),
            ],
        ],

        'dhl' => [
            'driver' => 'dhl',
            'credentials' => [
                'client_id' => env('DHL_CLIENT_ID'),
                'client_secret' => env('DHL_CLIENT_SECRET'),
            ],
            'sandbox' => env('DHL_SANDBOX', false),
        ],
    ],
];
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-rate-shopping-engine.md](03-rate-shopping-engine.md)
