<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Policies;

use Illuminate\Database\Eloquent\Model;

/**
 * Policy for payment method authorization in the customer portal.
 *
 * Ensures users can only manage their own payment methods.
 */
class PaymentMethodPolicy
{
    /**
     * Determine whether the user can view any payment methods.
     */
    public function viewAny(Model $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the payment method.
     */
    public function view(Model $user, Model $paymentMethod): bool
    {
        return $this->ownsPaymentMethod($user, $paymentMethod);
    }

    /**
     * Determine whether the user can add a payment method.
     */
    public function create(Model $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the payment method.
     */
    public function update(Model $user, Model $paymentMethod): bool
    {
        return $this->ownsPaymentMethod($user, $paymentMethod);
    }

    /**
     * Determine whether the user can delete the payment method.
     */
    public function delete(Model $user, Model $paymentMethod): bool
    {
        return $this->ownsPaymentMethod($user, $paymentMethod);
    }

    /**
     * Determine whether the user can set the payment method as default.
     */
    public function setDefault(Model $user, Model $paymentMethod): bool
    {
        return $this->ownsPaymentMethod($user, $paymentMethod);
    }

    /**
     * Check if the user owns the payment method.
     */
    protected function ownsPaymentMethod(Model $user, Model $paymentMethod): bool
    {
        $userId = $this->resolveIdentifier($user, [$user->getKeyName()]);

        if ($userId === null) {
            return false;
        }

        $billableType = $this->resolveStringAttribute($paymentMethod, 'billable_type');
        $billableId = $this->resolveIdentifier($paymentMethod, ['billable_id']);

        if ($billableType !== null || $billableId !== null) {
            return $billableType === $user->getMorphClass()
                && $billableId !== null
                && (string) $billableId === (string) $userId;
        }

        $pmUserId = $this->resolveIdentifier($paymentMethod, ['user_id']);

        if ($pmUserId === null) {
            return false;
        }

        return (string) $userId === (string) $pmUserId;
    }

    /**
     * @param  array<int, string>  $attributeNames
     */
    private function resolveIdentifier(Model $model, array $attributeNames): int | string | null
    {
        $attributes = $model->getAttributes();

        foreach ($attributeNames as $attributeName) {
            if (! array_key_exists($attributeName, $attributes)) {
                continue;
            }

            $value = $attributes[$attributeName];

            if (is_int($value) || is_string($value)) {
                return $value;
            }
        }

        foreach ($attributeNames as $attributeName) {
            $value = $model->getRawOriginal($attributeName);

            if (is_int($value) || is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    private function resolveStringAttribute(Model $model, string $attributeName): ?string
    {
        $attributes = $model->getAttributes();

        if (array_key_exists($attributeName, $attributes) && is_string($attributes[$attributeName]) && $attributes[$attributeName] !== '') {
            return $attributes[$attributeName];
        }

        $value = $model->getRawOriginal($attributeName);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
