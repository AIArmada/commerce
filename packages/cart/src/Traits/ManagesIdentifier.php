<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

trait ManagesIdentifier
{
    /**
     * Get the cart identifier
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Set the cart identifier
     *
     * This creates a new cart instance with the specified identifier.
     * Useful for switching between different user/session carts at runtime.
     */
    public function setIdentifier(string $identifier): static
    {
        if ($this->identifier === $identifier) {
            return $this;
        }

        return new static(
            $this->storage,
            $identifier,
            $this->events,
            $this->instanceName,
            $this->eventsEnabled
        );
    }
}
