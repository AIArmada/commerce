<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Payment;

use AIArmada\CashierChip\Contracts\PaymentMethodStoreInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PaymentMethodStore implements PaymentMethodStoreInterface
{
    public function allForBillable(Model $billable): Collection
    {
        return $this->queryForBillable($billable)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForBillable(Model $billable, string $recurringToken): ?StoredPaymentMethod
    {
        // Tokens are encrypted at rest with a random IV, so ciphertext cannot
        // be matched in SQL; compare decrypted values after fetching.
        /** @var StoredPaymentMethod|null $paymentMethod */
        $paymentMethod = $this->queryForBillable($billable)->get()->first(
            fn (StoredPaymentMethod $paymentMethod): bool => $this->tokenMatches($paymentMethod, $recurringToken)
        );

        return $paymentMethod;
    }

    public function defaultForBillable(Model $billable): ?StoredPaymentMethod
    {
        /** @var StoredPaymentMethod|null $paymentMethod */
        $paymentMethod = $this->queryForBillable($billable)
            ->where('is_default', true)
            ->first();

        return $paymentMethod;
    }

    public function hasAnyForBillable(Model $billable): bool
    {
        return $this->queryForBillable($billable)->exists();
    }

    public function saveForBillable(
        Model $billable,
        string $recurringToken,
        array $attributes = [],
        bool $makeDefault = false,
    ): StoredPaymentMethod {
        $this->assertBillableWriteAllowed($billable);

        return DB::transaction(function () use ($billable, $recurringToken, $attributes, $makeDefault): StoredPaymentMethod {
            $paymentMethod = $this->findForBillable($billable, $recurringToken);

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
                $this->queryForBillable($billable)->update(['is_default' => false]);
                $paymentMethod->is_default = true;
            } else {
                $paymentMethod->is_default ??= false;
            }

            $paymentMethod->save();

            return $paymentMethod->refresh();
        });
    }

    public function setDefaultForBillable(Model $billable, string $recurringToken): ?StoredPaymentMethod
    {
        $this->assertBillableWriteAllowed($billable);

        return DB::transaction(function () use ($billable, $recurringToken): ?StoredPaymentMethod {
            $paymentMethod = $this->findForBillable($billable, $recurringToken);

            if ($paymentMethod === null) {
                if ($this->findAnyForBillable($billable, $recurringToken) !== null) {
                    throw new AuthorizationException('Payment method is not accessible for the current owner context.');
                }

                return null;
            }

            $this->queryForBillable($billable)->lockForUpdate()->update(['is_default' => false]);

            $paymentMethod->is_default = true;
            $paymentMethod->save();

            return $paymentMethod->refresh();
        });
    }

    public function deleteForBillable(Model $billable, string $recurringToken): void
    {
        $this->assertBillableWriteAllowed($billable);

        $paymentMethod = $this->findForBillable($billable, $recurringToken);

        if ($paymentMethod === null) {
            if ($this->findAnyForBillable($billable, $recurringToken) !== null) {
                throw new AuthorizationException('Payment method is not accessible for the current owner context.');
            }

            return;
        }

        $wasDefault = $paymentMethod->is_default;

        $paymentMethod->delete();

        if (! $wasDefault) {
            return;
        }

        $nextPaymentMethod = $this->allForBillable($billable)->first();

        if ($nextPaymentMethod instanceof StoredPaymentMethod) {
            $this->setDefaultForBillable($billable, $nextPaymentMethod->recurring_token);
        }
    }

    public function deleteAllForBillable(Model $billable): void
    {
        $this->assertBillableWriteAllowed($billable);

        $this->queryForBillable($billable)->delete();
    }

    /**
     * @return Builder<StoredPaymentMethod>
     */
    private function queryForBillable(Model $billable): Builder
    {
        /** @var Builder<StoredPaymentMethod> $query */
        $query = StoredPaymentMethod::query()
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

        if (! (bool) config('cashier-chip.features.owner.validate_billable_owner', true)) {
            return;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            if (OwnerContext::isExplicitGlobal()) {
                return;
            }

            throw new AuthorizationException('Owner context is required to mutate payment methods.');
        }

        if (is_a($owner, $billable::class) && (string) $owner->getKey() === (string) $billable->getKey()) {
            return;
        }

        if (is_a($owner, $billable::class)) {
            throw new AuthorizationException('Cross-tenant payment method write blocked.');
        }

        $ownerType = $billable->getAttribute('owner_type');
        $ownerId = $billable->getAttribute('owner_id');

        if (is_string($ownerType) && $ownerType !== '' && is_scalar($ownerId)) {
            if ($ownerType === $owner->getMorphClass() && (string) $ownerId === (string) $owner->getKey()) {
                return;
            }

            throw new AuthorizationException('Cross-tenant payment method write blocked.');
        }

        if (! method_exists($billable, 'scopeForOwner') && ! method_exists($billable::class, 'ownerScopeConfig')) {
            return;
        }

        $columns = OwnerTupleColumns::forModelClass($billable::class);

        /** @var Builder<Model> $query */
        $query = $billable->newQuery();

        $exists = OwnerQuery::applyToEloquentBuilder(
            $query->withoutGlobalScope(OwnerScope::class),
            $owner,
            false,
            $columns->ownerTypeColumn,
            $columns->ownerIdColumn,
        )
            ->whereKey($billable->getKey())
            ->exists();

        if (! $exists) {
            throw new AuthorizationException('Cross-tenant payment method write blocked.');
        }
    }
}
