<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Services;

use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Exceptions\ManualRedemptionNotAllowedException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherReservations;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherValidation;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Runtime\VoucherCreator;
use AIArmada\Vouchers\Runtime\VoucherDeletion;
use AIArmada\Vouchers\Runtime\VoucherServiceInterface;
use AIArmada\Vouchers\Runtime\VoucherSnapshot;
use AIArmada\Vouchers\Runtime\VoucherUpdater;
use AIArmada\Vouchers\Runtime\VoucherUsageHistory;
use AIArmada\Vouchers\Runtime\VoucherUsageRecorder;
use AIArmada\Vouchers\Runtime\WalletClaim;
use AIArmada\Vouchers\Runtime\WalletRemoval;
use AIArmada\Vouchers\States\Active;
use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class VoucherService implements VoucherServiceInterface
{
    use QueriesVouchers;

    public function __construct(
        protected VoucherValidation $validator,
        private readonly VoucherCatalog $catalog,
    ) {}

    public function find(string $code, ?string $voucherId = null): ?VoucherData
    {
        return $this->catalog->find($code, $voucherId);
    }

    public function findOrFail(string $code): VoucherData
    {
        $voucher = $this->find($code);

        if (! $voucher) {
            throw VoucherNotFoundException::withCode($code);
        }

        return $voucher;
    }

    public function invalidate(string $code): void
    {
        $this->catalog->invalidate($code);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): VoucherData
    {
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = $this->normalizeCode($data['code']);
        }

        $data['status'] ??= Active::class;

        /** @var VoucherModel $voucher */
        $voucher = app(VoucherCreator::class)->handle($data);

        return VoucherSnapshot::fromModel($voucher);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $code, array $data): VoucherData
    {
        $voucher = app(VoucherUpdater::class)->handle($this->normalizeCode($code), $data);

        return VoucherSnapshot::fromModel($voucher);
    }

    public function delete(string $code): bool
    {
        return app(VoucherDeletion::class)->handle($code);
    }

    public function validate(string $code, mixed $cart): VoucherValidationResult
    {
        return $this->validator->validate($code, $cart);
    }

    public function getRemainingUses(string $code): int
    {
        return $this->catalog->getRemainingUses($code);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordUsage(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null,
        ?VoucherModel $voucherModel = null
    ): void {
        app(VoucherUsageRecorder::class)->handle(
            code: $code,
            discountAmount: $discountAmount,
            channel: $channel,
            metadata: $metadata,
            redeemedBy: $redeemedBy,
            notes: $notes,
            voucherModel: $voucherModel,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function redeemManually(
        string $code,
        Money $discountAmount,
        ?string $reference = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
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

        $this->recordUsage(
            code: $code,
            discountAmount: $discountAmount,
            channel: $channel,
            metadata: array_merge($metadata ?? [], ['reference' => $reference]),
            redeemedBy: $redeemedBy,
            notes: $notes,
            voucherModel: $voucher
        );
    }

    /**
     * @return EloquentCollection<int, VoucherUsage>
     */
    public function getUsageHistory(string $code, int $limit = 100): EloquentCollection
    {
        return app(VoucherUsageHistory::class)->handle($code, $limit);
    }

    /**
     * Add a voucher to the owner's wallet.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function addToWallet(string $code, Model $holder, ?array $metadata = null): VoucherWallet
    {
        return app(WalletClaim::class)->handle($code, $holder, $metadata);
    }

    /**
     * Remove a voucher from the owner's wallet (if not redeemed).
     */
    public function removeFromWallet(string $code, Model $holder): bool
    {
        return app(WalletRemoval::class)->handle($code, $holder);
    }

    /**
     * Reserve a voucher for a checkout session.
     *
     * Reservations prevent the voucher from being used elsewhere
     * during the checkout process.
     */
    public function reserve(string $code, string $sessionId, ?string $voucherId = null): void
    {
        app(VoucherReservations::class)->reserve($code, $sessionId, $voucherId);
    }

    public function release(string $code, ?string $sessionId = null, ?string $voucherId = null): void
    {
        app(VoucherReservations::class)->release($code, $sessionId, $voucherId);
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
        $redeemedBy = $order?->customer;

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

        $this->recordUsage(
            code: $voucher->code,
            discountAmount: $money,
            channel: 'checkout',
            metadata: $metadata,
            redeemedBy: $redeemedBy,
            voucherModel: $voucher
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
    private function percentageRedeemDiscount(VoucherModel $voucher, ?Model $redeemedBy): int
    {
        $subtotal = $redeemedBy?->getAttribute('subtotal');

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
