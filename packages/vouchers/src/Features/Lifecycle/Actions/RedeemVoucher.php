<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Exceptions\ManualRedemptionNotAllowedException;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherRedemption;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherUsageRecorder;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Features\Lifecycle\Queries\VoucherSnapshot;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Services\VoucherDiscountCalculator;
use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use Illuminate\Validation\ValidationException;

final class RedeemVoucher implements VoucherRedemption
{
    use QueriesVouchers;

    public function __construct(private readonly VoucherUsageRecorder $recorder) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function redeemManually(
        string $code,
        Money $discountAmount,
        ?string $reference = null,
        ?array $metadata = null,
        ?RecordReference $redeemedBy = null,
        ?string $notes = null
    ): void {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        if (
            config('vouchers.redemption.manual_requires_flag', true)
            && ! $voucher->allowsManualRedemption()
        ) {
            throw ManualRedemptionNotAllowedException::forVoucher($voucher->code);
        }

        /** @var string $channel */
        $channel = config('vouchers.redemption.manual_channel', 'manual');

        $this->recorder->handle(
            code: $code,
            discountAmount: $discountAmount,
            channel: $channel,
            metadata: array_merge($metadata ?? [], ['reference' => $reference]),
            redeemedBy: $redeemedBy,
            notes: $notes,
            voucherId: $voucher->id
        );
    }

    /**
     * Redeem a voucher after successful order completion.
     */
    public function redeem(
        string $code,
        string $orderId,
        ?int $discountAmount = null,
        ?string $currency = null,
        ?string $voucherId = null,
    ): void {
        $voucher = $this->voucherIdentityQuery($code, $voucherId)->firstOrFail();

        /** @var VoucherModel $voucher */
        $currency = $this->resolveMoneyCurrency($currency, $voucher);

        $voucherType = $voucher->type instanceof VoucherType
            ? $voucher->type
            : VoucherType::tryFrom((string) $voucher->type);

        $order = $this->resolveOrder($orderId);
        $redeemedBy = $order !== null && $order->customer_type !== null && $order->customer_id !== null
            ? new RecordReference((string) $order->customer_type, (string) $order->customer_id)
            : null;

        // Calculate the discount amount based on voucher type
        $discount = $discountAmount ?? match ($voucherType) {
            VoucherType::Percentage => $this->percentageRedeemDiscount($voucher, $order),
            VoucherType::Fixed => (int) ($voucher->value ?? 0),
            default => 0,
        };
        if ($discount < 0) {
            throw ValidationException::withMessages(['discount_amount' => 'Voucher discount cannot be negative.']);
        }

        $money = Money::{$currency}($discount);
        $metadata = [
            'order_id' => $orderId,
        ];

        if ($order instanceof Order) {
            $orderNumber = $order->getAttribute('order_number');

            if (is_string($orderNumber) && mb_trim($orderNumber) !== '') {
                $metadata['order_number'] = mb_trim($orderNumber);
            }

            foreach (['subtotal', 'discount_total', 'grand_total'] as $attribute) {
                $value = $order->getAttribute($attribute);

                if (is_numeric($value)) {
                    $metadata[$attribute] = (int) $value;
                }
            }
        }

        $this->recorder->handle(
            code: $voucher->code,
            discountAmount: $money,
            channel: 'checkout',
            metadata: $metadata,
            redeemedBy: $redeemedBy,
            voucherId: $voucher->id
        );
    }

    private function resolveMoneyCurrency(?string $currency, VoucherModel $voucher): string
    {
        $normalized = mb_strtoupper(mb_trim($currency ?? $voucher->currency));

        if (! preg_match('/^[A-Z]{3}$/', $normalized) || ! array_key_exists($normalized, Currency::getCurrencies())) {
            throw ValidationException::withMessages(['currency' => 'The redemption currency must be a supported 3-letter code.']);
        }

        return $normalized;
    }

    /**
     * Recompute a percentage discount from the order subtotal when the
     * caller did not supply the allocated checkout amount, so the usage
     * row records the real discount instead of zero.
     */
    private function percentageRedeemDiscount(VoucherModel $voucher, ?Order $order): int
    {
        $subtotal = $order?->getAttribute('subtotal');

        if (! is_numeric($subtotal) || (int) $subtotal <= 0) {
            return 0;
        }

        return app(VoucherDiscountCalculator::class)->calculate(VoucherSnapshot::fromModel($voucher), (int) $subtotal);
    }

    private function resolveOrder(string $orderId): ?Order
    {
        if (! class_exists(Order::class) || ! (new Order)->getConnection()->getSchemaBuilder()->hasTable((new Order)->getTable())) {
            return null;
        }

        return Order::query()
            ->select(['id', 'order_number', 'subtotal', 'discount_total', 'grand_total', 'customer_type', 'customer_id'])
            ->find($orderId);
    }
}
