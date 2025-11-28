<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\CashierChip;
use AIArmada\CashierChip\Exceptions\CustomerAlreadyCreated;
use AIArmada\CashierChip\Exceptions\InvalidCustomer;
use AIArmada\Chip\DataObjects\Client;

trait ManagesCustomer
{
    /**
     * Retrieve the CHIP customer ID.
     */
    public function chipId(): ?string
    {
        return $this->chip_id;
    }

    /**
     * Determine if the customer has a CHIP customer ID.
     */
    public function hasChipId(): bool
    {
        return ! is_null($this->chip_id);
    }

    /**
     * Determine if the customer has a CHIP customer ID and throw an exception if not.
     *
     * @throws \AIArmada\CashierChip\Exceptions\InvalidCustomer
     */
    protected function assertCustomerExists(): void
    {
        if (! $this->hasChipId()) {
            throw InvalidCustomer::notYetCreated($this);
        }
    }

    /**
     * Create a CHIP customer for the given model.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws \AIArmada\CashierChip\Exceptions\CustomerAlreadyCreated
     */
    public function createAsChipCustomer(array $options = []): Client
    {
        if ($this->hasChipId()) {
            throw CustomerAlreadyCreated::exists($this);
        }

        $options = array_merge([
            'email' => $this->chipEmail(),
            'full_name' => $this->chipName(),
            'phone' => $this->chipPhone(),
            'country' => $this->chipCountry(),
        ], $options);

        // Add address if available
        if ($address = $this->chipAddress()) {
            $options = array_merge($options, $address);
        }

        $client = CashierChip::chip()->createClient(array_filter($options));

        $this->chip_id = $client->id;
        $this->save();

        return $client;
    }

    /**
     * Update the underlying CHIP customer information for the model.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateChipCustomer(array $options = []): Client
    {
        $this->assertCustomerExists();

        return CashierChip::chip()->updateClient($this->chip_id, $options);
    }

    /**
     * Get the CHIP customer instance for the current user or create one.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOrGetChipCustomer(array $options = []): Client
    {
        if ($this->hasChipId()) {
            return $this->asChipCustomer();
        }

        return $this->createAsChipCustomer($options);
    }

    /**
     * Update the CHIP customer information for the current user or create one.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateOrCreateChipCustomer(array $options = []): Client
    {
        if ($this->hasChipId()) {
            return $this->updateChipCustomer($options);
        }

        return $this->createAsChipCustomer($options);
    }

    /**
     * Sync the customer's information to CHIP for the current user or create one.
     *
     * @param  array<string, mixed>  $options
     */
    public function syncOrCreateChipCustomer(array $options = []): Client
    {
        if ($this->hasChipId()) {
            return $this->syncChipCustomerDetails();
        }

        return $this->createAsChipCustomer($options);
    }

    /**
     * Get the CHIP customer for the model.
     */
    public function asChipCustomer(): Client
    {
        $this->assertCustomerExists();

        return CashierChip::chip()->getClient($this->chip_id);
    }

    /**
     * Get the name that should be synced to CHIP.
     */
    public function chipName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Get the email address that should be synced to CHIP.
     */
    public function chipEmail(): ?string
    {
        return $this->email ?? null;
    }

    /**
     * Get the phone number that should be synced to CHIP.
     */
    public function chipPhone(): ?string
    {
        return $this->phone ?? null;
    }

    /**
     * Get the country that should be synced to CHIP.
     */
    public function chipCountry(): ?string
    {
        return $this->country ?? 'MY';
    }

    /**
     * Get the address that should be synced to CHIP.
     *
     * @return array<string, string>
     */
    public function chipAddress(): array
    {
        return [];

        // return [
        //     'street_address' => '1 Main St.',
        //     'city' => 'Kuala Lumpur',
        //     'zip_code' => '50000',
        //     'state' => 'Wilayah Persekutuan',
        //     'country' => 'MY',
        // ];
    }

    /**
     * Sync the customer's information to CHIP.
     */
    public function syncChipCustomerDetails(): Client
    {
        return $this->updateChipCustomer(array_filter([
            'full_name' => $this->chipName(),
            'email' => $this->chipEmail(),
            'phone' => $this->chipPhone(),
            'country' => $this->chipCountry(),
            ...$this->chipAddress(),
        ]));
    }

    /**
     * Get the CHIP supported currency used by the customer.
     */
    public function preferredCurrency(): string
    {
        return config('cashier-chip.currency', 'MYR');
    }

    /**
     * Format the given amount into a displayable currency.
     */
    protected function formatAmount(int $amount): string
    {
        return CashierChip::formatAmount($amount, $this->preferredCurrency());
    }
}
