# Shipping Vision: Rate Shopping Engine

> **Document:** 03 of 11  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

The Rate Shopping Engine aggregates quotes from multiple carriers and applies selection rules to recommend the optimal shipping option. This enables customers to compare rates at checkout while allowing merchants to configure carrier preferences.

---

## Core Components

### RateShoppingEngine Service

```php
<?php

namespace AIArmada\Shipping\Services;

use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Contracts\RateSelectionStrategy;
use Illuminate\Support\Collection;

class RateShoppingEngine
{
    public function __construct(
        private readonly ShippingManager $shippingManager,
        private readonly RateCache $rateCache,
        private readonly RateSelectionStrategy $selectionStrategy,
        private readonly array $config
    ) {}

    /**
     * Get all available rates from all carriers.
     *
     * @param  array<PackageData>  $packages
     * @return Collection<int, RateQuoteData>
     */
    public function getAllRates(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): Collection {
        $cacheKey = $this->buildCacheKey($origin, $destination, $packages);

        return $this->rateCache->remember($cacheKey, function () use ($origin, $destination, $packages, $options) {
            return $this->fetchRatesFromAllCarriers($origin, $destination, $packages, $options);
        });
    }

    /**
     * Get the best rate based on selection strategy.
     */
    public function getBestRate(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): ?RateQuoteData {
        $allRates = $this->getAllRates($origin, $destination, $packages, $options);

        if ($allRates->isEmpty()) {
            return $this->getFallbackRate($destination, $packages);
        }

        return $this->selectionStrategy->select($allRates, $options);
    }

    /**
     * Get rates for a specific cart.
     */
    public function getRatesForCart(Cart $cart, AddressData $destination): Collection
    {
        $origin = $this->resolveOrigin($cart);
        $packages = $this->cartToPackages($cart);

        $rates = $this->getAllRates($origin, $destination, $packages);

        // Apply cart-specific modifiers
        return $rates->map(function (RateQuoteData $rate) use ($cart) {
            return $this->applyCartModifiers($rate, $cart);
        });
    }

    protected function fetchRatesFromAllCarriers(...$args): Collection
    {
        $drivers = $this->shippingManager->getDriversForDestination($args[1]);

        return $drivers
            ->map(fn ($driver) => rescue(
                fn () => $driver->getRates(...$args),
                collect(),
                false
            ))
            ->flatten(1)
            ->sortBy('rate');
    }

    protected function applyCartModifiers(RateQuoteData $rate, Cart $cart): RateQuoteData
    {
        // Check for free shipping threshold
        if ($cart->getSubtotal() >= $this->config['free_shipping_threshold']) {
            if ($rate->service === $this->config['free_shipping_method']) {
                return $rate->withRate(0)->withNote('Free shipping applied');
            }
        }

        return $rate;
    }
}
```

---

## Selection Strategies

### Strategy Interface

```php
<?php

namespace AIArmada\Shipping\Contracts;

use AIArmada\Shipping\Data\RateQuoteData;
use Illuminate\Support\Collection;

interface RateSelectionStrategy
{
    /**
     * Select the best rate from available options.
     *
     * @param  Collection<int, RateQuoteData>  $rates
     */
    public function select(Collection $rates, array $options = []): ?RateQuoteData;
}
```

### CheapestRateStrategy

```php
<?php

namespace AIArmada\Shipping\Strategies;

class CheapestRateStrategy implements RateSelectionStrategy
{
    public function select(Collection $rates, array $options = []): ?RateQuoteData
    {
        return $rates->sortBy('rate')->first();
    }
}
```

### FastestRateStrategy

```php
<?php

namespace AIArmada\Shipping\Strategies;

class FastestRateStrategy implements RateSelectionStrategy
{
    public function select(Collection $rates, array $options = []): ?RateQuoteData
    {
        return $rates->sortBy('estimatedDays')->first();
    }
}
```

### PreferredCarrierStrategy

```php
<?php

namespace AIArmada\Shipping\Strategies;

class PreferredCarrierStrategy implements RateSelectionStrategy
{
    public function __construct(
        private readonly array $priority
    ) {}

    public function select(Collection $rates, array $options = []): ?RateQuoteData
    {
        // First, try preferred carrier
        foreach ($this->priority as $carrier) {
            $rate = $rates->firstWhere('carrier', $carrier);
            if ($rate) {
                return $rate;
            }
        }

        // Fallback to cheapest
        return $rates->sortBy('rate')->first();
    }
}
```

### BalancedRateStrategy

```php
<?php

namespace AIArmada\Shipping\Strategies;

class BalancedRateStrategy implements RateSelectionStrategy
{
    /**
     * Score = (speed_weight × speed_score) + (cost_weight × cost_score)
     */
    public function select(Collection $rates, array $options = []): ?RateQuoteData
    {
        $speedWeight = $options['speed_weight'] ?? 0.5;
        $costWeight = $options['cost_weight'] ?? 0.5;

        $maxRate = $rates->max('rate');
        $maxDays = $rates->max('estimatedDays');

        return $rates
            ->map(function (RateQuoteData $rate) use ($maxRate, $maxDays, $speedWeight, $costWeight) {
                $speedScore = 1 - ($rate->estimatedDays / $maxDays);
                $costScore = 1 - ($rate->rate / $maxRate);
                $totalScore = ($speedWeight * $speedScore) + ($costWeight * $costScore);

                return ['rate' => $rate, 'score' => $totalScore];
            })
            ->sortByDesc('score')
            ->first()['rate'] ?? null;
    }
}
```

---

## Rate Quote Data Structure

```php
<?php

namespace AIArmada\Shipping\Data;

use Spatie\LaravelData\Data;

class RateQuoteData extends Data
{
    public function __construct(
        public readonly string $carrier,
        public readonly string $service,
        public readonly int $rate, // In cents
        public readonly string $currency,
        public readonly int $estimatedDays,
        public readonly ?string $estimatedDeliveryDate = null,
        public readonly ?string $serviceDescription = null,
        public readonly ?array $restrictions = null,
        public readonly bool $calculatedLocally = false,
        public readonly ?string $quoteId = null,
        public readonly ?\DateTimeInterface $expiresAt = null,
        public readonly ?string $note = null,
    ) {}

    public function withRate(int $rate): self
    {
        return new self(
            carrier: $this->carrier,
            service: $this->service,
            rate: $rate,
            currency: $this->currency,
            estimatedDays: $this->estimatedDays,
            estimatedDeliveryDate: $this->estimatedDeliveryDate,
            serviceDescription: $this->serviceDescription,
            restrictions: $this->restrictions,
            calculatedLocally: $this->calculatedLocally,
            quoteId: $this->quoteId,
            expiresAt: $this->expiresAt,
            note: $this->note,
        );
    }

    public function withNote(string $note): self
    {
        return new self(...array_merge($this->toArray(), ['note' => $note]));
    }

    public function getFormattedRate(): string
    {
        return number_format($this->rate / 100, 2) . ' ' . $this->currency;
    }

    public function getDeliveryEstimate(): string
    {
        if ($this->estimatedDeliveryDate) {
            return $this->estimatedDeliveryDate;
        }

        $days = $this->estimatedDays;
        return $days === 1 ? '1 business day' : "{$days} business days";
    }
}
```

---

## Cart Integration

### ShippingConditionProvider

```php
<?php

namespace AIArmada\Shipping\Cart;

use AIArmada\Cart\Contracts\ConditionProviderInterface;
use AIArmada\Cart\Conditions\ShippingCondition;
use AIArmada\Shipping\Services\RateShoppingEngine;

class ShippingConditionProvider implements ConditionProviderInterface
{
    public function __construct(
        private readonly RateShoppingEngine $rateEngine
    ) {}

    public function getConditions(Cart $cart): Collection
    {
        $destination = $cart->getShippingAddress();

        if (! $destination) {
            return collect();
        }

        $selectedRate = $this->getSelectedRate($cart, $destination);

        if (! $selectedRate) {
            return collect();
        }

        return collect([
            new ShippingCondition(
                name: $selectedRate->service,
                type: 'shipping',
                value: $selectedRate->rate,
                attributes: [
                    'carrier' => $selectedRate->carrier,
                    'service' => $selectedRate->service,
                    'estimated_days' => $selectedRate->estimatedDays,
                    'quote_id' => $selectedRate->quoteId,
                ],
            ),
        ]);
    }

    protected function getSelectedRate(Cart $cart, AddressData $destination): ?RateQuoteData
    {
        // Check if a specific rate was selected
        $selectedMethod = $cart->metadata['selected_shipping_method'] ?? null;

        if ($selectedMethod) {
            $allRates = $this->rateEngine->getRatesForCart($cart, $destination);
            return $allRates->first(fn ($rate) => 
                $rate->carrier === $selectedMethod['carrier'] &&
                $rate->service === $selectedMethod['service']
            );
        }

        // Otherwise, get best rate
        return $this->rateEngine->getBestRate(
            $this->getOrigin($cart),
            $destination,
            $this->cartToPackages($cart)
        );
    }
}
```

### Checkout Flow Integration

```php
<?php

// In checkout controller or Livewire component

public function getShippingOptions(): array
{
    $engine = app(RateShoppingEngine::class);
    $cart = $this->cart;
    $destination = $this->getShippingAddress();

    return $engine
        ->getRatesForCart($cart, $destination)
        ->map(fn (RateQuoteData $rate) => [
            'id' => "{$rate->carrier}:{$rate->service}",
            'label' => "{$rate->carrier} - {$rate->service}",
            'description' => $rate->getDeliveryEstimate(),
            'price' => $rate->getFormattedRate(),
            'rate' => $rate->rate,
        ])
        ->toArray();
}

public function selectShippingMethod(string $methodId): void
{
    [$carrier, $service] = explode(':', $methodId);

    $this->cart->setMetadata('selected_shipping_method', [
        'carrier' => $carrier,
        'service' => $service,
    ]);

    $this->cart->recalculate();
}
```

---

## Free Shipping Rules

### FreeShippingRule Model

```php
<?php

namespace AIArmada\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class FreeShippingRule extends Model
{
    protected $fillable = [
        'name',
        'type', // threshold, product, category, coupon
        'condition', // JSON conditions
        'applies_to', // carriers, methods
        'priority',
        'active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'condition' => 'array',
        'applies_to' => 'array',
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
```

### FreeShippingEvaluator

```php
<?php

namespace AIArmada\Shipping\Services;

class FreeShippingEvaluator
{
    public function evaluate(Cart $cart): ?FreeShippingResult
    {
        $rules = FreeShippingRule::query()
            ->active()
            ->ordered()
            ->get();

        foreach ($rules as $rule) {
            if ($this->ruleApplies($rule, $cart)) {
                return new FreeShippingResult(
                    applies: true,
                    rule: $rule,
                    message: $rule->success_message,
                );
            }
        }

        // Check if close to threshold
        $nearestThreshold = $this->findNearestThreshold($cart);
        if ($nearestThreshold) {
            $remaining = $nearestThreshold->threshold - $cart->getSubtotal();
            return new FreeShippingResult(
                applies: false,
                nearThreshold: true,
                remainingAmount: $remaining,
                message: "Add {$this->formatMoney($remaining)} more for free shipping!",
            );
        }

        return null;
    }
}
```

---

## Rate Caching

### RateCache Service

```php
<?php

namespace AIArmada\Shipping\Services;

use Illuminate\Support\Facades\Cache;

class RateCache
{
    public function __construct(
        private readonly int $ttl = 300 // 5 minutes
    ) {}

    public function remember(string $key, callable $callback): Collection
    {
        return Cache::tags(['shipping', 'rates'])->remember(
            $key,
            $this->ttl,
            $callback
        );
    }

    public function forget(string $key): void
    {
        Cache::tags(['shipping', 'rates'])->forget($key);
    }

    public function flush(): void
    {
        Cache::tags(['shipping', 'rates'])->flush();
    }

    public function buildKey(AddressData $origin, AddressData $destination, array $packages): string
    {
        return 'rate:' . md5(serialize([
            'origin' => $origin->postCode,
            'destination' => $destination->postCode,
            'weight' => collect($packages)->sum('weight'),
        ]));
    }
}
```

---

## Rate Display Components

### Blade Component Example

```blade
{{-- resources/views/components/shipping-options.blade.php --}}

<div class="shipping-options space-y-2">
    @foreach($rates as $rate)
        <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:border-primary-500
            {{ $selected === $rate['id'] ? 'border-primary-500 bg-primary-50' : 'border-gray-200' }}">
            <input 
                type="radio" 
                name="shipping_method" 
                value="{{ $rate['id'] }}"
                wire:click="selectShippingMethod('{{ $rate['id'] }}')"
                {{ $selected === $rate['id'] ? 'checked' : '' }}
                class="mr-3"
            >
            <div class="flex-1">
                <div class="font-medium">{{ $rate['label'] }}</div>
                <div class="text-sm text-gray-500">{{ $rate['description'] }}</div>
            </div>
            <div class="font-semibold">
                @if($rate['rate'] === 0)
                    <span class="text-green-600">FREE</span>
                @else
                    {{ $rate['price'] }}
                @endif
            </div>
        </label>
    @endforeach

    @if($freeShippingPromo)
        <div class="p-2 bg-yellow-50 border border-yellow-200 rounded text-sm">
            🚚 {{ $freeShippingPromo }}
        </div>
    @endif
</div>
```

---

## Navigation

**Previous:** [02-multi-carrier-architecture.md](02-multi-carrier-architecture.md)  
**Next:** [04-shipment-lifecycle.md](04-shipment-lifecycle.md) - Shipment Model & Status Workflow

---

*The Rate Shopping Engine transforms shipping from a cost center into a competitive advantage, enabling optimal carrier selection while maintaining a seamless customer experience.*
