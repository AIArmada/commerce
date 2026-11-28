<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Persistence;

trait AtomicInventoryLevelPersistence
{
    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if (! $this->exists || $this->suppressLedgerAudit || ! $this->isDirty('quantity_on_hand')) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            app(InventoryLevelStore::class)->guardDirectOnHandChange($this);

            return parent::save($options);
        });
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(fn (): ?bool => parent::delete());
    }
}
