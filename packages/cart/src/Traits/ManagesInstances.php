<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Storage\StorageInterface;

trait ManagesInstances
{
    /**
     * Get the current instance name
     */
    public function instance(): string
    {
        return $this->instanceName;
    }

    /**
     * Get the storage interface
     */
    public function storage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * Set the current cart instance
     */
    public function setInstance(string $name): static
    {
        return new static(
            $this->storage,
            $this->identifier,
            $this->events,
            $name,
            $this->eventsEnabled
        );
    }

    /**
     * Check if a cart exists in storage
     *
     * @param  string|null  $identifier  Cart identifier (defaults to current)
     * @param  string|null  $instance  Instance name (defaults to current)
     */
    public function exists(?string $identifier = null, ?string $instance = null): bool
    {
        $identifier ??= $this->getIdentifier();
        $instance ??= $this->instance();

        return $this->storage->has($identifier, $instance);
    }

    /**
     * Completely remove cart from storage
     *
     * Unlike clear() which empties items but keeps the cart structure,
     * destroy() completely removes the cart from storage.
     *
     * @param  string|null  $identifier  Cart identifier (defaults to current)
     * @param  string|null  $instance  Instance name (defaults to current)
     */
    public function destroy(?string $identifier = null, ?string $instance = null): void
    {
        $identifier ??= $this->getIdentifier();
        $instance ??= $this->instance();

        $this->storage->forget($identifier, $instance);
    }

    /**
     * Get all cart instances for an identifier
     *
     * @param  string|null  $identifier  Cart identifier (defaults to current)
     * @return array<string> Array of instance names
     */
    public function instances(?string $identifier = null): array
    {
        $identifier ??= $this->getIdentifier();

        return $this->storage->getInstances($identifier);
    }

    /**
     * Clear the entire cart
     */
    public function clear(): bool
    {
        $this->storage->forget($this->getIdentifier(), $this->instance());

        if ($this->eventsEnabled && $this->events) {
            $this->events->dispatch(new CartCleared($this));
        }

        return true;
    }
}
