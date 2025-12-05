# Rate Shopping Engine

> **Document:** 3 of 9  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision

---

## Overview

Build a **real-time rate comparison engine** that queries multiple carriers, calculates all-in costs, and presents optimized recommendations to merchants.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      RATE REQUEST                                │
│  Origin → Destination → Package Details → Options               │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                   RATE CALCULATOR                                │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │ Zone Mapper  │  │ Surcharge    │  │ Dimensional Weight     │ │
│  │              │  │ Calculator   │  │ Calculator             │ │
│  └──────────────┘  └──────────────┘  └────────────────────────┘ │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────────┐│
│  │                    Rate Aggregator                           ││
│  │   (Parallel queries to all available carriers)              ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐            │
│  │  J&T    │  │ PosLaju │  │  DHL    │  │  GDex   │            │
│  │ (Card)  │  │  (API)  │  │  (API)  │  │ (Card)  │            │
│  └─────────┘  └─────────┘  └─────────┘  └─────────┘            │
│                                                                  │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                   RATE COMPARISON RESULT                         │
│  Sorted by: Cost | Speed | Reliability | Recommended            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Rate Request

### RateRequest Data Object

```php
final readonly class RateRequest
{
    public function __construct(
        public AddressData $origin,
        public AddressData $destination,
        public PackageData $package,
        public ?DateTimeInterface $shipDate = null,
        public ?MoneyData $declaredValue = null,
        public bool $includeCod = false,
        public ?MoneyData $codAmount = null,
        public bool $includeInsurance = false,
        public array $requiredServices = [],
        public ?string $currency = 'MYR',
    ) {}
}
```

---

## Rate Result

### RateQuote Data Object

```php
final readonly class RateQuote
{
    public function __construct(
        public string $carrierId,
        public string $carrierName,
        public string $serviceCode,
        public string $serviceName,
        public MoneyData $baseRate,
        public MoneyData $fuelSurcharge,
        public MoneyData $remoteSurcharge,
        public MoneyData $residentialSurcharge,
        public MoneyData $codFee,
        public MoneyData $insuranceFee,
        public MoneyData $totalRate,
        public int $estimatedDaysMin,
        public int $estimatedDaysMax,
        public ?DateTimeInterface $estimatedDeliveryBy,
        public string $currency,
        public ?float $reliabilityScore = null,
        public array $warnings = [],
        public array $metadata = [],
    ) {}

    public function isRecommended(): bool
    {
        return $this->metadata['recommended'] ?? false;
    }
}
```

### RateCollection

```php
final class RateCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<RateQuote>
     */
    private array $quotes = [];

    public function add(RateQuote $quote): self
    {
        $this->quotes[] = $quote;
        return $this;
    }

    public function sortByCost(): self
    {
        usort($this->quotes, fn ($a, $b) => 
            $a->totalRate->amountMinor <=> $b->totalRate->amountMinor
        );
        return $this;
    }

    public function sortBySpeed(): self
    {
        usort($this->quotes, fn ($a, $b) => 
            $a->estimatedDaysMin <=> $b->estimatedDaysMin
        );
        return $this;
    }

    public function cheapest(): ?RateQuote
    {
        return $this->sortByCost()->first();
    }

    public function fastest(): ?RateQuote
    {
        return $this->sortBySpeed()->first();
    }

    public function recommended(): ?RateQuote
    {
        foreach ($this->quotes as $quote) {
            if ($quote->isRecommended()) {
                return $quote;
            }
        }
        return $this->cheapest();
    }
}
```

---

## Rate Calculator

### RateCalculatorService

```php
final class RateCalculatorService
{
    public function __construct(
        private readonly ShippingManager $manager,
        private readonly ZoneMapper $zoneMapper,
        private readonly SurchargeCalculator $surchargeCalculator,
        private readonly RateCache $cache,
    ) {}

    /**
     * Get rates from all available carriers.
     */
    public function getRates(RateRequest $request): RateComparisonResult
    {
        $carriers = $this->manager->available();
        $quotes = new RateCollection();
        $errors = [];

        // Query carriers in parallel
        $results = $this->queryCarriersParallel($carriers, $request);

        foreach ($results as $carrierId => $result) {
            if ($result instanceof RateCollection) {
                foreach ($result as $quote) {
                    // Apply standard surcharges
                    $quote = $this->applySurcharges($quote, $request);
                    $quotes->add($quote);
                }
            } else {
                $errors[$carrierId] = $result;
            }
        }

        // Score and recommend
        $quotes = $this->scoreAndRecommend($quotes, $request);

        return new RateComparisonResult(
            quotes: $quotes,
            request: $request,
            errors: $errors,
        );
    }

    private function queryCarriersParallel(array $carriers, RateRequest $request): array
    {
        $promises = [];

        foreach ($carriers as $carrierId => $carrier) {
            $promises[$carrierId] = async(function () use ($carrier, $request) {
                $capabilities = $carrier->getCapabilities();
                
                if ($capabilities->supportsRateQuotes) {
                    return $carrier->getRates($request);
                }
                
                // Use cached rate cards
                return $this->getRatesFromRateCard($carrier, $request);
            });
        }

        return await($promises);
    }

    private function applySurcharges(RateQuote $quote, RateRequest $request): RateQuote
    {
        $surcharges = $this->surchargeCalculator->calculate($quote, $request);
        
        return new RateQuote(
            ...$quote->toArray(),
            fuelSurcharge: $surcharges->fuel,
            remoteSurcharge: $surcharges->remote,
            residentialSurcharge: $surcharges->residential,
            totalRate: $this->calculateTotal($quote, $surcharges),
        );
    }

    private function scoreAndRecommend(RateCollection $quotes, RateRequest $request): RateCollection
    {
        foreach ($quotes as $quote) {
            // Calculate reliability score based on historical data
            $score = $this->calculateReliabilityScore($quote);
            $quote = $quote->withReliabilityScore($score);
        }

        // Mark best value as recommended (balance of cost, speed, reliability)
        $bestValue = $this->findBestValue($quotes);
        if ($bestValue) {
            $bestValue->markAsRecommended();
        }

        return $quotes;
    }
}
```

---

## Zone Mapping

### ZoneMapper Service

```php
final class ZoneMapper
{
    /**
     * Get the zone for a given origin-destination pair.
     */
    public function getZone(string $originPostcode, string $destinationPostcode): Zone
    {
        // Determine zone based on postal codes
        $originState = $this->getStateFromPostcode($originPostcode);
        $destinationState = $this->getStateFromPostcode($destinationPostcode);

        if ($originState === $destinationState) {
            return Zone::SameState;
        }

        if ($this->isPeninsula($originState) && $this->isPeninsula($destinationState)) {
            return Zone::WestMalaysia;
        }

        if ($this->isEastMalaysia($originState) && $this->isEastMalaysia($destinationState)) {
            return Zone::EastMalaysia;
        }

        return Zone::CrossMalaysia;
    }

    /**
     * Check if postcode is in a remote area.
     */
    public function isRemoteArea(string $postcode): bool
    {
        return in_array($postcode, $this->remotePostcodes);
    }

    /**
     * Get delivery estimate for zone.
     */
    public function getTransitDays(Zone $zone, string $serviceType): array
    {
        return match ($zone) {
            Zone::SameState => ['min' => 1, 'max' => 2],
            Zone::WestMalaysia => ['min' => 2, 'max' => 3],
            Zone::EastMalaysia => ['min' => 2, 'max' => 4],
            Zone::CrossMalaysia => ['min' => 3, 'max' => 5],
        };
    }
}
```

---

## Surcharge Calculator

### SurchargeCalculator Service

```php
final class SurchargeCalculator
{
    public function calculate(RateQuote $quote, RateRequest $request): Surcharges
    {
        return new Surcharges(
            fuel: $this->calculateFuelSurcharge($quote),
            remote: $this->calculateRemoteSurcharge($request->destination),
            residential: $this->calculateResidentialSurcharge($request->destination),
            oversized: $this->calculateOversizedSurcharge($request->package),
            cod: $this->calculateCodFee($request),
            insurance: $this->calculateInsuranceFee($request),
        );
    }

    private function calculateFuelSurcharge(RateQuote $quote): MoneyData
    {
        $fuelPercentage = config('shipping.surcharges.fuel_percentage', 0.15);
        $amount = (int) ($quote->baseRate->amountMinor * $fuelPercentage);
        
        return new MoneyData($amount, $quote->currency);
    }

    private function calculateRemoteSurcharge(AddressData $destination): MoneyData
    {
        if (! app(ZoneMapper::class)->isRemoteArea($destination->postcode)) {
            return new MoneyData(0, 'MYR');
        }

        return new MoneyData(
            config('shipping.surcharges.remote_fee_minor', 500),
            'MYR'
        );
    }

    private function calculateCodFee(RateRequest $request): MoneyData
    {
        if (! $request->includeCod || ! $request->codAmount) {
            return new MoneyData(0, 'MYR');
        }

        $percentage = config('shipping.surcharges.cod_percentage', 0.03);
        $minFee = config('shipping.surcharges.cod_min_fee_minor', 100);
        
        $fee = max($minFee, (int) ($request->codAmount->amountMinor * $percentage));
        
        return new MoneyData($fee, 'MYR');
    }
}
```

---

## Rate Cards (Cached Rates)

### RateCardService

```php
final class RateCardService
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    /**
     * Get rates from cached rate card.
     */
    public function getRates(string $carrierId, RateRequest $request): RateCollection
    {
        $rateCard = $this->loadRateCard($carrierId);
        $zone = app(ZoneMapper::class)->getZone(
            $request->origin->postcode,
            $request->destination->postcode
        );
        
        $billingWeight = $this->calculateBillingWeight($request->package);
        $quotes = new RateCollection();

        foreach ($rateCard->services as $serviceCode => $rates) {
            $rate = $this->findRate($rates, $zone, $billingWeight);
            
            if ($rate) {
                $quotes->add(new RateQuote(
                    carrierId: $carrierId,
                    carrierName: $rateCard->carrierName,
                    serviceCode: $serviceCode,
                    serviceName: $rates['name'],
                    baseRate: new MoneyData($rate, 'MYR'),
                    // ... other fields
                ));
            }
        }

        return $quotes;
    }

    private function calculateBillingWeight(PackageData $package): int
    {
        $volumetric = $package->volumetricWeight(5000);
        return max($package->weightGrams, $volumetric);
    }

    private function loadRateCard(string $carrierId): RateCard
    {
        return $this->cache->remember(
            "rate_card:{$carrierId}",
            now()->addDay(),
            fn () => RateCard::where('carrier_id', $carrierId)
                ->where('is_active', true)
                ->latest()
                ->first()
        );
    }
}
```

---

## Rate Card Schema

### Database Structure

```php
Schema::create('shipping_rate_cards', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('carrier_id');
    $table->string('name');
    $table->date('effective_from');
    $table->date('effective_until')->nullable();
    $table->json('rates');
    $table->json('surcharges');
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['carrier_id', 'is_active']);
});
```

### Rate Card JSON Structure

```json
{
  "services": {
    "EZ": {
      "name": "Domestic Standard",
      "zones": {
        "same_state": {
          "0_500": 550,
          "501_1000": 750,
          "1001_2000": 1050,
          "per_500g_after": 200
        },
        "west_malaysia": {
          "0_500": 650,
          "501_1000": 850,
          "1001_2000": 1150,
          "per_500g_after": 250
        }
      },
      "transit_days": {
        "same_state": { "min": 1, "max": 2 },
        "west_malaysia": { "min": 2, "max": 3 }
      }
    }
  }
}
```

---

## Caching Strategy

```
Rate Request → Cache Key Generation → Cache Lookup
                                          ↓
                                   ┌──────────────┐
                                   │  Cache Hit   │→ Return cached rates
                                   └──────────────┘
                                          ↓ (Miss)
                               Query carriers/rate cards
                                          ↓
                               Cache result (5-15 min TTL)
                                          ↓
                               Return fresh rates
```

---

## Navigation

**Previous:** [02-multi-carrier-abstraction.md](02-multi-carrier-abstraction.md)  
**Next:** [04-carrier-selection-rules.md](04-carrier-selection-rules.md)
