<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Contracts;

use AIArmada\Chip\Data\ClientData;

interface BillableIdentity
{
    public function chipId(): ?string;

    public function hasChipId(): bool;

    /**
     * @param  array<string, mixed>  $options
     */
    public function createOrGetChipCustomer(array $options = []): ClientData;

    public function chipName(): ?string;

    public function chipEmail(): ?string;

    public function chipPhone(): ?string;

    public function chipCountry(): ?string;

    /**
     * @return array<string, string>
     */
    public function chipAddress(): array;

    public function preferredCurrency(): string;
}
