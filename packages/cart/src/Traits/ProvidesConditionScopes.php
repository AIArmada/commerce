<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

trait ProvidesConditionScopes
{
    /**
     * @param  callable(self):iterable  $resolver
     */
    /** @phpstan-ignore-next-line */
    /** @phpstan-ignore-next-line */
    public function resolveShipmentsUsing(callable $resolver): static
    {
        /** @phpstan-ignore property.notFound */
        $this->shipmentResolver = $resolver;

        return $this;
    }

    public function hasShipmentResolver(): bool
    {
        /** @phpstan-ignore property.notFound */
        return $this->shipmentResolver !== null;
    }

    /**
     * @return iterable<mixed>
     */
    /**
     * @return iterable<mixed>
     */
    public function getShipments(): iterable
    {
        if (! $this->hasShipmentResolver()) {
            return [];
        }

        /** @phpstan-ignore property.notFound */
        return ($this->shipmentResolver)($this);
    }

    /**
     * Register a resolver that returns payment data.
     *
     * @param  callable(self):iterable  $resolver
     */
    /** @phpstan-ignore-next-line */
    public function resolvePaymentsUsing(callable $resolver): static
    {
        /** @phpstan-ignore property.notFound */
        $this->paymentResolver = $resolver;

        return $this;
    }

    public function hasPaymentResolver(): bool
    {
        /** @phpstan-ignore property.notFound */
        return $this->paymentResolver !== null;
    }

    /**
     * @return iterable<mixed>
     */
    public function getPayments(): iterable
    {
        if (! $this->hasPaymentResolver()) {
            return [];
        }

        /** @phpstan-ignore property.notFound */
        return ($this->paymentResolver)($this);
    }

    /**
     * Register a resolver that returns fulfillment data.
     *
     * @param  callable(self):iterable  $resolver
     */
    /** @phpstan-ignore-next-line */
    public function resolveFulfillmentsUsing(callable $resolver): static
    {
        /** @phpstan-ignore property.notFound */
        $this->fulfillmentResolver = $resolver;

        return $this;
    }

    public function hasFulfillmentResolver(): bool
    {
        /** @phpstan-ignore property.notFound */
        return $this->fulfillmentResolver !== null;
    }

    /**
     * @return iterable<mixed>
     */
    public function getFulfillments(): iterable
    {
        if (! $this->hasFulfillmentResolver()) {
            return [];
        }

        /** @phpstan-ignore property.notFound */
        return ($this->fulfillmentResolver)($this);
    }
}
