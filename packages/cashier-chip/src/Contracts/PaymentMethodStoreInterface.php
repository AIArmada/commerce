<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Contracts;

use AIArmada\CashierChip\Data\StoredPaymentMethodData;
use AIArmada\CommerceSupport\Data\RecordReference;

interface PaymentMethodStoreInterface
{
    /**
     * @return list<StoredPaymentMethodData>
     */
    public function allForBillable(RecordReference $billable): array;

    public function findForBillable(RecordReference $billable, string $recurringToken): ?StoredPaymentMethodData;

    public function defaultForBillable(RecordReference $billable): ?StoredPaymentMethodData;

    public function hasAnyForBillable(RecordReference $billable): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveForBillable(
        RecordReference $billable,
        string $recurringToken,
        array $attributes = [],
        bool $makeDefault = false,
    ): StoredPaymentMethodData;

    public function setDefaultForBillable(RecordReference $billable, string $recurringToken): ?StoredPaymentMethodData;

    public function assertBillableAccess(RecordReference $billable): void;

    public function deleteForBillable(RecordReference $billable, string $recurringToken): void;

    public function deleteAllForBillable(RecordReference $billable): void;
}
