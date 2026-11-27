<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\PaymentMethods\Persistence;

use AIArmada\CashierChip\Billing\CustomerOwnerResolver;
use AIArmada\CashierChip\Contracts\PaymentMethodStoreInterface;
use AIArmada\CashierChip\Data\StoredPaymentMethodData;
use AIArmada\CashierChip\Features\PaymentMethods\Models\StoredPaymentMethod;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class PaymentMethodStore implements PaymentMethodStoreInterface
{
    public function __construct(private CustomerOwnerResolver $ownerResolver) {}

    /** @return list<StoredPaymentMethodData> */
    public function allForBillable(RecordReference $billable): array
    {
        return $this->queryForBillable($this->resolveBillable($billable))
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get()->map(fn (StoredPaymentMethod $method): StoredPaymentMethodData => $this->toData($method))->values()->all();
    }

    public function findForBillable(RecordReference $billable, string $recurringToken): ?StoredPaymentMethodData
    {
        $method = $this->findModelForBillable($this->resolveBillable($billable), $recurringToken);

        return $method === null ? null : $this->toData($method);
    }

    private function findModelForBillable(Model $billable, string $recurringToken): ?StoredPaymentMethod
    {
        // Tokens are encrypted at rest with a random IV, so ciphertext cannot
        // be matched in SQL; compare decrypted values after fetching.
        /** @var StoredPaymentMethod|null $paymentMethod */
        $paymentMethod = $this->queryForBillable($billable)->get()->first(
            fn (StoredPaymentMethod $paymentMethod): bool => $this->tokenMatches($paymentMethod, $recurringToken)
        );

        return $paymentMethod;
    }

    public function defaultForBillable(RecordReference $billable): ?StoredPaymentMethodData
    {
        /** @var StoredPaymentMethod|null $paymentMethod */
        $paymentMethod = $this->queryForBillable($this->resolveBillable($billable))
            ->where('is_default', true)
            ->first();

        return $paymentMethod === null ? null : $this->toData($paymentMethod);
    }

    public function hasAnyForBillable(RecordReference $billable): bool
    {
        return $this->queryForBillable($this->resolveBillable($billable))->exists();
    }

    public function saveForBillable(
        RecordReference $billable,
        string $recurringToken,
        array $attributes = [],
        bool $makeDefault = false,
    ): StoredPaymentMethodData {
        $billable = $this->resolveBillable($billable);
        if (mb_trim($recurringToken) === '') {
            throw new InvalidArgumentException('Recurring token must not be empty.');
        }

        return $billable->getConnection()->transaction(function () use ($billable, $recurringToken, $attributes, $makeDefault): StoredPaymentMethodData {
            $this->lockBillable($billable);
            $paymentMethod = $this->findModelForBillable($billable, $recurringToken);

            if ($paymentMethod === null) {
                if ($this->findAnyForBillable($billable, $recurringToken) !== null) {
                    throw new AuthorizationException('Payment method is not accessible for the current owner context.');
                }

                $paymentMethod = new StoredPaymentMethod;
            }

            $paymentMethod->billable()->associate($billable);
            $paymentMethod->recurring_token = $recurringToken;
            $paymentMethod->type = $attributes['type'] ?? $paymentMethod->type;
            $paymentMethod->brand = $attributes['brand'] ?? $paymentMethod->brand;
            $paymentMethod->last_four = $attributes['last_four'] ?? $paymentMethod->last_four;
            $paymentMethod->metadata = $attributes['metadata'] ?? $paymentMethod->metadata;

            $shouldSetDefault = $makeDefault || ! $this->queryForBillable($billable)->lockForUpdate()->where('is_default', true)->exists();

            if ($shouldSetDefault) {
                $otherMethods = $this->queryForBillable($billable)->where('is_default', true);

                if ($paymentMethod->exists) {
                    $otherMethods->whereKeyNot($paymentMethod->getKey());
                }

                $otherMethods->update(['is_default' => false]);
                $paymentMethod->is_default = true;
            } else {
                $paymentMethod->is_default ??= false;
            }

            $paymentMethod->save();

            return $this->toData($paymentMethod->refresh());
        });
    }

    public function setDefaultForBillable(RecordReference $billable, string $recurringToken): ?StoredPaymentMethodData
    {
        $billable = $this->resolveBillable($billable);

        return $billable->getConnection()->transaction(function () use ($billable, $recurringToken): ?StoredPaymentMethodData {
            $this->lockBillable($billable);
            $paymentMethod = $this->findModelForBillable($billable, $recurringToken);

            if ($paymentMethod === null) {
                if ($this->findAnyForBillable($billable, $recurringToken) !== null) {
                    throw new AuthorizationException('Payment method is not accessible for the current owner context.');
                }

                return null;
            }

            $this->queryForBillable($billable)->where('is_default', true)->whereKeyNot($paymentMethod->getKey())->update(['is_default' => false]);

            $paymentMethod->is_default = true;
            $paymentMethod->save();

            return $this->toData($paymentMethod->refresh());
        });
    }

    public function deleteForBillable(RecordReference $billable, string $recurringToken): void
    {
        $billable = $this->resolveBillable($billable);

        $billable->getConnection()->transaction(function () use ($billable, $recurringToken): void {
            $this->lockBillable($billable);
            $paymentMethod = $this->findModelForBillable($billable, $recurringToken);

            if ($paymentMethod === null) {
                if ($this->findAnyForBillable($billable, $recurringToken) !== null) {
                    throw new AuthorizationException('Payment method is not accessible for the current owner context.');
                }

                return;
            }

            $wasDefault = $paymentMethod->is_default;
            $paymentMethod->delete();

            if ($wasDefault) {
                $nextPaymentMethod = $this->queryForBillable($billable)->orderByDesc('created_at')->first();

                if ($nextPaymentMethod instanceof StoredPaymentMethod) {
                    $nextPaymentMethod->is_default = true;
                    $nextPaymentMethod->save();
                }
            }
        });
    }

    public function deleteAllForBillable(RecordReference $billable): void
    {
        $billable = $this->resolveBillable($billable);

        $billable->getConnection()->transaction(function () use ($billable): void {
            $this->lockBillable($billable);
            $this->queryForBillable($billable)->delete();
        });
    }

    public function assertBillableAccess(RecordReference $billable): void
    {
        $this->resolveBillable($billable);
    }

    private function resolveBillable(RecordReference $reference): Model
    {
        $billable = EloquentRecordReference::resolve($reference) ?? throw new AuthorizationException('Payment method billable is not accessible.');
        $this->assertBillableWriteAllowed($billable);

        return $billable;
    }

    private function toData(StoredPaymentMethod $method): StoredPaymentMethodData
    {
        return new StoredPaymentMethodData(
            id: (string) $method->getKey(),
            token: $method->recurringToken(),
            type: $method->type,
            brand: $method->brand,
            last_four: $method->last_four,
            is_default: (bool) $method->is_default,
            metadata: $method->metadata ?? [],
        );
    }

    private function lockBillable(Model $billable): void
    {
        $billable->newQuery()->whereKey($billable->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @return Builder<StoredPaymentMethod>
     */
    private function queryForBillable(Model $billable): Builder
    {
        /** @var Builder<StoredPaymentMethod> $query */
        $query = StoredPaymentMethod::query()->forOwner(includeGlobal: false)
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey());

        return $query;
    }

    private function findAnyForBillable(Model $billable, string $recurringToken): ?StoredPaymentMethod
    {
        /** @var StoredPaymentMethod|null $paymentMethod */
        $paymentMethod = StoredPaymentMethod::query()
            ->withoutOwnerScope()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->get()
            ->first(fn (StoredPaymentMethod $paymentMethod): bool => $this->tokenMatches($paymentMethod, $recurringToken));

        return $paymentMethod;
    }

    private function tokenMatches(StoredPaymentMethod $paymentMethod, string $recurringToken): bool
    {
        $stored = $paymentMethod->recurring_token;

        if (! is_string($stored) || $stored === '' || $recurringToken === '') {
            return false;
        }

        return hash_equals($stored, $recurringToken);
    }

    private function assertBillableWriteAllowed(Model $billable): void
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', false)) {
            return;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            if (OwnerContext::isExplicitGlobal()) {
                return;
            }

            throw new AuthorizationException('Owner context is required to mutate payment methods.');
        }

        if (! (bool) config('cashier-chip.features.owner.validate_billable_owner', true)) {
            return;
        }

        if ($this->ownerResolver->canAccess($billable, $owner)) {
            return;
        }

        throw new AuthorizationException('Cross-tenant payment method write blocked.');
    }
}
