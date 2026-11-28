<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Queries;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingMode;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherValidation;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherEligibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

final class VoucherValidator implements VoucherValidation
{
    use NormalizesVoucherCodes;

    public function __construct(
        private readonly TargetingEngineInterface $targetingEngine,
        private readonly VoucherEligibility $eligibility,
    ) {}

    public function validate(string $code, mixed $cart): VoucherValidationResult
    {
        $code = $this->normalizeCode($code);
        $cartCurrency = $cart instanceof Cart
            ? $cart->getCheckoutCurrency()
            : (is_array($cart) ? ($cart['currency'] ?? null) : null);

        $user = $this->getUser();
        $eligibility = $this->eligibility->validate(
            $code,
            $cartCurrency,
            $user?->getMorphClass(),
            $user === null ? null : (string) $user->getKey(),
        );

        if (! $eligibility->isValid) {
            return $eligibility;
        }

        $snapshot = $eligibility->getDetail('voucher');

        if (! is_array($snapshot)) {
            return VoucherValidationResult::invalid('Voucher not found.');
        }

        $voucher = VoucherData::fromArray($snapshot);

        // Check minimum cart value
        if (config('vouchers.validation.check_min_cart_value', true) && $voucher->minCartValue) {
            $cartTotal = $this->getCartTotal($cart);

            if ($cartTotal < $voucher->minCartValue) {
                $formattedMinValue = MoneyFormatter::formatMinor($voucher->minCartValue, $voucher->currency);

                Log::info('Voucher min cart value check failed', [
                    'code' => $code,
                    'cartTotal' => $cartTotal,
                    'min_cart_value' => $voucher->minCartValue,
                ]);

                return VoucherValidationResult::invalid(
                    "Minimum cart value of {$formattedMinValue} required.",
                    ['min_cart_value' => $voucher->minCartValue, 'current_cart_value' => $cartTotal]
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

        return VoucherValidationResult::valid(['voucher' => $voucher->toArray()]);
    }

    protected function getUser(): ?Model
    {
        $user = Auth::user();

        return $user instanceof Model ? $user : null;
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

        // Unknown cart shapes fail closed: a zero total can never satisfy a
        // minimum cart value requirement. Log at debug level so new caller
        // shapes are visible without failing validation outright.
        Log::debug('Voucher cart total fell back to zero for an unknown cart shape', [
            'shape' => is_object($cart) ? $cart::class : get_debug_type($cart),
        ]);

        return 0;
    }

    /**
     * Validate voucher targeting rules against the cart context.
     */
    protected function validateTargeting(VoucherData $voucher, mixed $cart): VoucherValidationResult
    {
        $targetingData = $this->parseTargetingDefinition($voucher->targetDefinition);

        if ($targetingData === null) {
            return VoucherValidationResult::valid();
        }

        // Fail closed: non-custom mode with an empty rules array means the targeting
        // definition was provided but is invalid — do not grant eligibility.
        if (isset($targetingData['__empty_rules'])) {
            return VoucherValidationResult::invalid(
                'You do not meet the eligibility requirements for this voucher.',
                ['targeting_failed' => true]
            );
        }

        // Fail closed: targeting rules exist but the context carries no line
        // items to evaluate them against (array snapshot without a live
        // cart). Granting eligibility blindly would let product-restricted
        // vouchers apply to ineligible orders.
        if (! $cart instanceof Cart) {
            return VoucherValidationResult::invalid(
                'You do not meet the eligibility requirements for this voucher.',
                ['targeting_failed' => true]
            );
        }

        $context = TargetingContext::fromCart($cart);
        $result = $this->targetingEngine->evaluate($targetingData, $context);

        if (! $result) {
            return VoucherValidationResult::invalid(
                'You do not meet the eligibility requirements for this voucher.',
                ['targeting_failed' => true]
            );
        }

        return VoucherValidationResult::valid();
    }

    /**
     * Parse target_definition into targeting engine format.
     *
     * @param  array<string, mixed>|null  $targetDefinition
     * @return array<string, mixed>|null
     */
    protected function parseTargetingDefinition(?array $targetDefinition): ?array
    {
        if ($targetDefinition === null) {
            return null;
        }

        // Stored data may contain a cart-condition target (scope, phase, application)
        // rather than targeting/eligibility rules (mode, rules, expression). When no
        // targeting-specific keys exist, only known cart-condition targets bypass eligibility.
        $hasTargeting = array_key_exists('targeting', $targetDefinition);
        $targeting = $hasTargeting ? $targetDefinition['targeting'] : null;

        if ($this->hasConditionTargetKeys($targetDefinition) && ! $this->isConditionTargetDefinition($targetDefinition)) {
            return $this->invalidTargetingDefinition();
        }

        if ($hasTargeting && (! is_array($targeting) || $targeting === [])) {
            return $this->invalidTargetingDefinition();
        }

        if ($targeting === null) {
            if ($this->isConditionTargetDefinition($targetDefinition)) {
                return null;
            }

            $targeting = $targetDefinition;
        }

        if (! is_array($targeting) || empty($targeting)) {
            return null;
        }

        $modeValue = $targeting['mode'] ?? TargetingMode::All->value;

        if ($modeValue instanceof TargetingMode) {
            $mode = $modeValue;
        } elseif (is_string($modeValue)) {
            $mode = TargetingMode::tryFrom($modeValue);

            if ($mode === null) {
                return $this->invalidTargetingDefinition();
            }
        } else {
            return $this->invalidTargetingDefinition();
        }

        /** @var array<int, array<string, mixed>> $rules */
        $rules = [];
        if (isset($targeting['rules']) && is_array($targeting['rules'])) {
            $rules = array_values($targeting['rules']);
        }

        /** @var array<string, mixed>|null $expression */
        $expression = null;
        if ($mode === TargetingMode::Custom && isset($targeting['expression']) && is_array($targeting['expression'])) {
            $expression = $targeting['expression'];
        }

        if (empty($rules) && $expression === null) {
            // Any mode with no rules and no expression is a degenerate/misconfigured
            // targeting definition. Fail closed (sentinel) for ALL modes — including Custom —
            // rather than silently granting eligibility.
            return $this->invalidTargetingDefinition($mode);
        }

        $data = [
            'mode' => $mode->value,
            'rules' => $rules,
        ];

        if ($expression !== null) {
            $data['expression'] = $expression;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $targetDefinition
     */
    private function isConditionTargetDefinition(array $targetDefinition): bool
    {
        if (! isset($targetDefinition['scope'], $targetDefinition['phase'], $targetDefinition['application'])) {
            return false;
        }

        try {
            ConditionTarget::from($targetDefinition);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function invalidTargetingDefinition(TargetingMode $mode = TargetingMode::All): array
    {
        return ['mode' => $mode->value, 'rules' => [], '__empty_rules' => true];
    }

    /**
     * @param  array<string, mixed>  $targetDefinition
     */
    private function hasConditionTargetKeys(array $targetDefinition): bool
    {
        return array_key_exists('scope', $targetDefinition)
            || array_key_exists('phase', $targetDefinition)
            || array_key_exists('application', $targetDefinition);
    }
}
