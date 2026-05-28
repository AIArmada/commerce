<?php

declare(strict_types=1);

namespace AIArmada\Chip\Contracts;

use AIArmada\Chip\Models\ChipCustomerLink;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use Illuminate\Database\Eloquent\Model;

interface ChipCustomerDirectoryInterface
{
    public function findForSubject(Model $subject): ?ChipCustomerLink;

    public function findByChipCustomerId(string $chipCustomerId, ?Model $owner = null): ?ChipCustomerLink;

    public function getChipCustomerId(Model $subject): ?string;

    public function hasChipCustomerId(Model $subject): bool;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function link(Model $subject, string $chipCustomerId, array $metadata = []): ChipCustomerLink;

    public function ensureForSubject(Model $subject, CustomerInterface $customer): ChipCustomerLink;

    public function syncForSubject(Model $subject, CustomerInterface $customer): ChipCustomerLink;
}
