<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Services;

use AIArmada\Cart\Cart;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Targeting\TargetingConfiguration;
use AIArmada\Vouchers\Targeting\TargetingContext;
use AIArmada\Vouchers\Targeting\TargetingEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class VoucherValidator
{
    public function __construct(
        protected OwnerResolverInterface $ownerResolver
    ) {}

    public function validate(string $code, mixed $cart): VoucherValidationResult
    {
        $code = $this->normalizeCode($code);

        // Find voucher
        $voucher = $this->query()
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            return VoucherValidationResult::invalid('Voucher not found.');
        }

        // Check start date (before status check, as time-based validations are more specific)
        if (! $voucher->hasStarted()) {
            return VoucherValidationResult::invalid(
                'Voucher is not yet available.',
                ['starts_at' => $voucher->starts_at]
            );
        }

        // Check expiry (before status check, as time-based validations are more specific)
        if ($voucher->isExpired()) {
            return VoucherValidationResult::invalid(
                'Voucher has expired.',
                ['expires_at' => $voucher->expires_at]
            );
        }

        // Check status (after time-based checks)
        if (! $voucher->isActive()) {
            if ($voucher->status === VoucherStatus::Paused) {
                return VoucherValidationResult::invalid('Voucher is paused.');
            }

            if ($voucher->status === VoucherStatus::Expired) {
                return VoucherValidationResult::invalid('Voucher has expired.');
            }

            if ($voucher->status === VoucherStatus::Depleted) {
                return VoucherValidationResult::invalid('Voucher usage limit has been reached.');
            }

            return VoucherValidationResult::invalid('Voucher is not active.');
        }

        // Check global usage limit
        if (config('vouchers.validation.check_global_limit', true)) {
            if (! $voucher->hasUsageLimitRemaining()) {
                return VoucherValidationResult::invalid('Voucher usage limit has been reached.');
            }
        }

        // Check per-user usage limit
        if (config('vouchers.validation.check_user_limit', true) && $voucher->usage_limit_per_user) {
            $user = $this->getUser();
            if ($user) {
                $usageCount = VoucherUsage::where('voucher_id', $voucher->id)
                    ->where('redeemed_by_type', $user->getMorphClass())
                    ->where('redeemed_by_id', $user->getKey())
                    ->count();

                if ($usageCount >= $voucher->usage_limit_per_user) {
                    return VoucherValidationResult::invalid(
                        'You have already used this voucher the maximum number of times.'
                    );
                }
            }
        }

        // Check minimum cart value
        if (config('vouchers.validation.check_min_cart_value', true) && $voucher->min_cart_value) {
            $cartTotal = $this->getCartTotal($cart);

            if ($cartTotal < $voucher->min_cart_value) {
                $currency = mb_strtoupper($voucher->currency ?? config('vouchers.default_currency', 'MYR'));
                $formattedMinValue = (string) \Akaunting\Money\Money::{$currency}($voucher->min_cart_value);

                return VoucherValidationResult::invalid(
                    "Minimum cart value of {$formattedMinValue} required.",
                    ['min_cart_value' => $voucher->min_cart_value, 'current_cart_value' => $cartTotal]
                );
            }
        }

        // Check targeting rules
        if (config('vouchers.validation.check_targeting', true)) {
            $targetingResult = $this->validateTargeting($voucher, $cart);
            if (! $targetingResult->isValid) {
                return $targetingResult;
            }
        }

        return VoucherValidationResult::valid();;
    }

    /**
     * @return Builder<Voucher>
     */
    protected function query(): Builder
    {
        return Voucher::query()->forOwner(
            $this->resolveOwner(),
            (bool) config('vouchers.owner.include_global', true)
        );
    }

    protected function resolveOwner(): ?Model
    {
        if (! config('vouchers.owner.enabled', false)) {
            return null;
        }

        return $this->ownerResolver->resolve();
    }

    protected function getUser(): ?Model
    {
        $user = Auth::user();

        return $user instanceof Model ? $user : null;
    }

    protected function getUserIdentifier(): string
    {
        $userId = Auth::id();

        if ($userId !== null) {
            return (string) $userId;
        }

        return (string) Session::getId();
    }

    protected function getCartTotal(mixed $cart): int
    {
        // Handle different cart types
        if (is_object($cart) && method_exists($cart, 'getRawSubtotalWithoutConditions')) {
            /** @var int $subtotal */
            $subtotal = $cart->getRawSubtotalWithoutConditions();

            return $subtotal;
        }

        if (is_array($cart) && isset($cart['total'])) {
            /** @var scalar $total */
            $total = $cart['total'];

            return (int) $total;
        }

        return 0;
    }

    protected function normalizeCode(string $code): string
    {
        if (config('vouchers.code.auto_uppercase', true)) {
            return mb_strtoupper(mb_trim($code));
        }

        return mb_trim($code);
    }

    /**
     * Validate voucher targeting rules against the cart context.
     */
    protected function validateTargeting(Voucher $voucher, mixed $cart): VoucherValidationResult
    {
        // Parse targeting configuration from voucher
        $configuration = TargetingConfiguration::fromArray($voucher->target_definition);

        // No targeting rules = valid
        if ($configuration === null || ! $configuration->hasRules()) {
            return VoucherValidationResult::valid();
        }

        // Need a Cart object for targeting evaluation
        if (! $cart instanceof Cart) {
            // Cannot evaluate targeting without a proper Cart context
            return VoucherValidationResult::valid();
        }

        // Build targeting context
        $context = TargetingContext::fromCart($cart);

        // Evaluate targeting rules using the configuration data
        $engine = new TargetingEngine();
        $targetingData = [
            'mode' => $configuration->mode->value,
            'rules' => $configuration->rules,
        ];

        if ($configuration->expression !== null) {
            $targetingData['expression'] = $configuration->expression;
        }

        $result = $engine->evaluate($targetingData, $context);

        if (! $result) {
            return VoucherValidationResult::invalid(
                'You do not meet the eligibility requirements for this voucher.',
                ['targeting_failed' => true]
            );
        }

        return VoucherValidationResult::valid();
    }
}
