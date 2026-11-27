<?php

declare(strict_types=1);

namespace AIArmada\Chip\Contracts;

use AIArmada\Chip\Data\CustomerLinkData;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Data\RecordReference;

interface ChipCustomerDirectoryInterface
{
    public function findForSubject(RecordReference $subject): ?CustomerLinkData;

    public function findByChipCustomerId(string $chipCustomerId, ?RecordReference $owner = null): ?CustomerLinkData;

    public function getChipCustomerId(RecordReference $subject): ?string;

    public function hasChipCustomerId(RecordReference $subject): bool;

    public function assertCanCreate(RecordReference $subject): void;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function link(RecordReference $subject, string $chipCustomerId, array $metadata = []): CustomerLinkData;

    public function ensureForSubject(RecordReference $subject, CustomerInterface $customer): CustomerLinkData;

    public function syncForSubject(RecordReference $subject, CustomerInterface $customer): CustomerLinkData;
}
