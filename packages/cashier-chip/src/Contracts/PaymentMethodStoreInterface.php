<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Contracts;

use AIArmada\CashierChip\Payment\StoredPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface PaymentMethodStoreInterface
{
    /**
     * @return Collection<int, StoredPaymentMethod>
     */
    public function allForBillable(Model $billable): Collection;

    public function findForBillable(Model $billable, string $recurringToken): ?StoredPaymentMethod;

    public function defaultForBillable(Model $billable): ?StoredPaymentMethod;

    public function hasAnyForBillable(Model $billable): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveForBillable(
        Model $billable,
        string $recurringToken,
        array $attributes = [],
        bool $makeDefault = false,
    ): StoredPaymentMethod;

    public function setDefaultForBillable(Model $billable, string $recurringToken): ?StoredPaymentMethod;

    public function deleteForBillable(Model $billable, string $recurringToken): void;

    public function deleteAllForBillable(Model $billable): void;
}
