<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models\Traits;

trait AttributeTrait
{
    /**
     * Set item attributes
     *
     * @param  array<string, mixed>  $attributes
     */
    public function setAttributes(array $attributes): static
    {
        return new static(
            $this->id,
            $this->name,
            $this->price,
            $this->quantity,
            $attributes,
            $this->conditions->toArray(),
            $this->associatedModel
        );
    }

    /**
     * Add an attribute
     */
    public function addAttribute(string $key, mixed $value): static
    {
        $attributes = $this->attributes->toArray();
        $attributes[$key] = $value;

        return $this->setAttributes($attributes);
    }

    /**
     * Remove an attribute
     */
    public function removeAttribute(string $key): static
    {
        $attributes = $this->attributes->toArray();
        unset($attributes[$key]);

        return $this->setAttributes($attributes);
    }

    /**
     * Get attribute value
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes->get($key, $default);
    }

    /**
     * Check if attribute exists
     */
    public function hasAttribute(string $key): bool
    {
        return $this->attributes->has($key);
    }

    /**
     * Set item name
     */
    public function setName(string $name): static
    {
        $name = mb_trim($name);
        if (empty($name)) {
            throw new \AIArmada\Cart\Exceptions\InvalidCartItemException('Cart item name cannot be empty');
        }

        return new static(
            $this->id,
            $name,
            $this->price,
            $this->quantity,
            $this->attributes->toArray(),
            $this->conditions->toArray(),
            $this->associatedModel
        );
    }

    /**
     * Set item price
     */
    public function setPrice(float|int|string $price): static
    {
        $normalizedPrice = is_string($price) ? $this->sanitizeStringPrice($price) : $price;

        if ($normalizedPrice < 0) {
            throw new \AIArmada\Cart\Exceptions\InvalidCartItemException('Price cannot be negative');
        }

        return new static(
            $this->id,
            $this->name,
            $normalizedPrice,
            $this->quantity,
            $this->attributes->toArray(),
            $this->conditions->toArray(),
            $this->associatedModel
        );
    }
}
