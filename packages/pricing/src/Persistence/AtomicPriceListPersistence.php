<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Persistence;

use Illuminate\Support\Facades\DB;

trait AtomicPriceListPersistence
{
    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }
}
