<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Cart\Cart;
use AIArmada\Checkout\Contracts\DiscountProvider;
use AIArmada\Checkout\Data\DiscountCommitment;
use AIArmada\Checkout\Data\DiscountProposal;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\CheckoutCartResolver;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Runtime\VoucherServiceInterface;
use AIArmada\Vouchers\Services\VoucherDiscountCalculator;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Throwable;

final class VouchersAdapter implements DiscountProvider
{
    public function __construct(
        private readonly ?CheckoutCartResolver $cartResolver = null,
        private readonly ?DiscountCodeResolver $discountCodeResolver = null,
    ) {}

    /**
     * Apply vouchers to the checkout session.
     *
     * @param  array<string>  $codes
     * @return array{applied: array<array<string, mixed>>, discount: int}
     */
    public function applyVouchers(CheckoutSession $session, array $codes, bool $reserve = true): array
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return ['applied' => [], 'discount' => 0];
        }

        $voucherService = app(VoucherServiceInterface::class);
        $validationContext = $this->cartResolver()->resolveVoucherValidationContext($session);
        $liveCart = $validationContext instanceof Cart ? $validationContext : null;
        $codes = $this->mergeUnifiedVoucherCodes($session, $codes);

        $applied = [];
        $seenVoucherIds = [];
        $totalDiscount = 0;
        $allowMultiple = config('checkout.integrations.vouchers.allow_multiple', false);

        foreach ($codes as $code) {
            // Validate voucher
            $validation = $this->normalizeValidationResult(
                $voucherService->validate($code, $validationContext),
            );

            if (! $validation['valid']) {
                continue;
            }

            // Calculate discount
            $voucher = $validation['voucher'];

            if (! is_array($voucher) || isset($seenVoucherIds[$voucher['id']])) {
                continue;
            }

            $seenVoucherIds[$voucher['id']] = true;
            $code = (string) $voucher['code'];
            $discount = $this->calculateVoucherDiscount($voucher, (int) $session->subtotal, $liveCart);

            if ($discount > 0) {
                // Reserve the voucher
                if ($reserve) {
                    $voucherService->reserve($code, $session->id, (string) $voucher['id']);
                }

                // Dispatch only for an actual cart application. Discount evaluation must stay
                // side-effect free because it can run repeatedly during checkout retries.
                if ($reserve && $liveCart !== null && class_exists(VoucherApplied::class)) {
                    Event::dispatch(new VoucherApplied($liveCart, VoucherData::fromArray($voucher)));
                }

                $applied[] = [
                    'voucher_id' => $voucher['id'],
                    'code' => $code,
                    'type' => $voucher['type'],
                    'discount' => $discount,
                    'promotion_id' => $voucher['promotion_id'] ?? null,
                ];

                $totalDiscount += $discount;

                // Stop if multiple vouchers not allowed
                if (! $allowMultiple) {
                    break;
                }
            }
        }

        return ['applied' => $applied, 'discount' => $totalDiscount];
    }

    /**
     * Validate a voucher code.
     *
     * @return array{valid: bool, message: string|null, voucher: array<string, mixed>|null}
     */
    public function validateVoucher(string $code, CheckoutSession $session): array
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return ['valid' => false, 'message' => 'Vouchers not available', 'voucher' => null];
        }

        $voucherService = app(VoucherServiceInterface::class);

        return $this->normalizeValidationResult(
            $voucherService->validate($code, $this->cartResolver()->resolveVoucherValidationContext($session)),
        );
    }

    /**
     * Release a voucher reservation.
     *
     * @param  string|null  $sessionId  When provided, only releases that session's reservation
     */
    public function releaseVoucher(string $code, ?string $sessionId = null, ?string $voucherId = null): void
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return;
        }

        $voucherService = app(VoucherServiceInterface::class);
        $voucherService->release($code, $sessionId, $voucherId);
    }

    /**
     * Redeem vouchers after successful checkout.
     *
     * @param  array<string>  $codes
     */
    public function redeemVouchers(array $codes, string $orderId): void
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return;
        }

        $voucherService = app(VoucherServiceInterface::class);

        foreach ($codes as $code) {
            $voucherService->redeem($code, $orderId);
        }
    }

    /**
     * @param  array<string, mixed>  $voucher
     */
    private function calculateVoucherDiscount(array $voucher, int $subtotal, ?Cart $cart): int
    {
        if (! class_exists(VoucherDiscountCalculator::class)) {
            return 0;
        }

        $voucherData = VoucherData::fromArray($voucher);
        $discountCalculator = app(VoucherDiscountCalculator::class);

        return $discountCalculator->calculate($voucherData, $subtotal, $cart);
    }

    /**
     * @return array{valid: bool, message: string|null, voucher: array<string, mixed>|null}
     */
    private function normalizeValidationResult(
        VoucherValidationResult $validation,
    ): array {
        $voucher = null;

        if ($validation->isValid) {
            $snapshot = $validation->getDetail('voucher');

            if (! is_array($snapshot) || ! is_string($snapshot['id'] ?? null) || $snapshot['id'] === '') {
                return ['valid' => false, 'message' => 'Voucher not found.', 'voucher' => null];
            }

            $voucher = VoucherData::fromArray($snapshot)->toArray();
        }

        return [
            'valid' => $validation->isValid,
            'message' => $validation->reason,
            'voucher' => $voucher,
        ];
    }

    /**
     * @param  array<string>  $codes
     * @return array<string>
     */
    private function mergeUnifiedVoucherCodes(CheckoutSession $session, array $codes): array
    {
        $resolvedDiscountCode = $this->discountCodeResolver()->resolve($session);

        if ($resolvedDiscountCode->isVoucher()) {
            array_unshift($codes, $resolvedDiscountCode->code);
        }

        $normalized = array_filter(array_map(
            static fn (mixed $code): ?string => is_string($code) && mb_trim($code) !== '' ? mb_trim($code) : null,
            $codes,
        ));

        return array_values(array_unique($normalized));
    }

    private function cartResolver(): CheckoutCartResolver
    {
        return $this->cartResolver ?? app(CheckoutCartResolver::class);
    }

    private function discountCodeResolver(): DiscountCodeResolver
    {
        return $this->discountCodeResolver ?? app(DiscountCodeResolver::class);
    }

    public function providerKey(): string
    {
        return 'vouchers';
    }

    public function evaluate(CheckoutSession $session, array $discountData): array
    {
        $codes = $discountData['voucher_codes'] ?? [];
        $result = $this->applyVouchers($session, is_array($codes) ? $codes : [], reserve: false);
        $proposals = [];

        foreach ($result['applied'] as $applied) {
            $proposals[] = new DiscountProposal(
                providerKey: 'vouchers',
                candidateKey: 'voucher:' . ($applied['code'] ?? ''),
                requestedAmount: $applied['discount'] ?? 0,
                label: $applied['code'] ?? null,
                code: $applied['code'] ?? null,
                priority: 50,
                meta: ['voucher_id' => $applied['voucher_id'] ?? null],
            );
        }

        return $proposals;
    }

    public function commit(CheckoutSession $session, array $accepted): array
    {
        $vouchers = [];

        foreach ($accepted as $proposal) {
            $voucherId = $proposal->meta['voucher_id'] ?? null;

            if ($proposal->code === null || ! is_string($voucherId) || $voucherId === '') {
                throw new InvalidArgumentException('Voucher commitments require an exact voucher identity.');
            }

            $vouchers[$voucherId] = $proposal->code;
        }

        if ($vouchers === []) {
            return [];
        }

        $sessionId = (string) $session->getKey();
        $reservedVouchers = [];

        try {
            foreach ($vouchers as $voucherId => $code) {
                $this->voucherService()->reserve($code, $sessionId, (string) $voucherId);
                $reservedVouchers[$voucherId] = $code;
            }

            $this->dispatchVoucherAppliedEvents($session, $vouchers);
        } catch (Throwable $exception) {
            foreach ($reservedVouchers as $voucherId => $code) {
                $this->releaseVoucher($code, $sessionId, (string) $voucherId);
            }

            throw $exception;
        }

        $commitments = [];
        foreach ($accepted as $proposal) {
            $key = $proposal->providerKey . ':' . $proposal->candidateKey;
            $commitments[$key] = new DiscountCommitment(
                providerKey: 'vouchers',
                candidateKey: $proposal->candidateKey,
                appliedAmount: $proposal->requestedAmount,
                reservationToken: $proposal->code ?? $proposal->candidateKey,
                meta: $proposal->meta,
            );
        }

        return $commitments;
    }

    /**
     * Dispatch cart-application events after a checkout commitment is reserved.
     *
     * @param  array<string, string>  $vouchers
     */
    private function dispatchVoucherAppliedEvents(CheckoutSession $session, array $vouchers): void
    {
        if (! class_exists(VoucherApplied::class)) {
            return;
        }

        $cart = $this->cartResolver()->resolveVoucherValidationContext($session);

        if (! $cart instanceof Cart) {
            return;
        }

        $voucherService = $this->voucherService();

        foreach ($vouchers as $voucherId => $code) {
            $voucher = $voucherService->find($code, $voucherId);

            if ($voucher instanceof VoucherData) {
                Event::dispatch(new VoucherApplied($cart, $voucher));
            }
        }
    }

    private function voucherService(): VoucherServiceInterface
    {
        return app(VoucherServiceInterface::class);
    }

    public function release(CheckoutSession $session, array $commitments): void
    {
        // Redemption happens from CheckoutCompleted, after an order exists.
        // Rollback therefore only clears the temporary reservation.
        $sessionId = (string) $session->getKey();

        foreach ($commitments as $commitment) {
            $code = $commitment->reservationToken;

            $voucherId = $commitment->meta['voucher_id'] ?? null;

            if ($code === '' || ! is_string($voucherId) || $voucherId === '') {
                throw new InvalidArgumentException('Voucher commitments require an exact voucher identity.');
            }

            $this->releaseVoucher($code, $sessionId, $voucherId);
        }
    }
}
