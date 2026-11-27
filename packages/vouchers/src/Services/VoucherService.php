<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Services;

use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Runtime\WalletClaim;
use AIArmada\Vouchers\Runtime\VoucherCreator;
use AIArmada\Vouchers\Runtime\VoucherDeletion;
use AIArmada\Vouchers\Runtime\VoucherUsageRecorder;
use AIArmada\Vouchers\Runtime\WalletRemoval;
use AIArmada\Vouchers\Runtime\VoucherUpdater;
use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Runtime\VoucherServiceInterface;
use AIArmada\Vouchers\Runtime\VoucherReservations;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Exceptions\ManualRedemptionNotAllowedException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\Support\VoucherLookupCache;
use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class VoucherService implements VoucherServiceInterface
{
    use QueriesVouchers;

    public function __construct(
        protected VoucherValidator $validator,
        private readonly VoucherLookupCache $lookupCache,
    ) {}

    public function find(string $code): ?VoucherData
    {
        $normalizedCode = $this->normalizeCode($code);

        return $this->lookupCache->remember(
            $normalizedCode,
            $this->resolveOwner(),
            $this->shouldIncludeGlobal(),
            function () use ($normalizedCode): ?VoucherData {
                $voucher = $this->voucherQuery()
                    ->where('code', $normalizedCode)
                    ->first();

                return $voucher ? VoucherData::fromModel($voucher) : null;
            },
        );
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
        $this->lookupCache->forget($this->normalizeCode($code), $this->resolveOwner());
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

        // Owner auto-assignment is handled inside CreateVoucher Action.
        // Do NOT duplicate it here to keep a single source of truth.

        /** @var VoucherModel $voucher */
        $voucher = app(VoucherCreator::class)->handle($data);

        return VoucherData::fromModel($voucher);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $code, array $data): VoucherData
    {
        $voucher = app(VoucherUpdater::class)->handle($this->normalizeCode($code), $data);

        return VoucherData::fromModel($voucher);
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
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return 0;
        }

        return $voucher->getRemainingUses() ?? PHP_INT_MAX;
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
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return new EloquentCollection;
        }

        /** @var EloquentCollection<int, VoucherUsage> $result */
        $result = $voucher->usages()
            ->latest('used_at')
            ->limit(max(1, $limit))
            ->get();

        return $result;
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
    public function reserve(string $code, string $sessionId): void
    {
        app(VoucherReservations::class)->reserve($code, $sessionId);
    }

    public function release(string $code, ?string $sessionId = null): void
    {
        app(VoucherReservations::class)->release($code, $sessionId);
    }

    /**
     * Redeem a voucher after successful order completion.
     */
    public function redeem(
        string $code,
        string $orderId,
        ?int $discountAmount = null,
        ?string $currency = null,
    ): void {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        $currency = $this->resolveMoneyCurrency($currency, $voucher);

        $voucherType = $voucher->type instanceof VoucherType
            ? $voucher->type
            : VoucherType::tryFrom((string) $voucher->type);

        $redeemedBy = $this->resolveRedeemedByOrder($orderId);

        // Calculate the discount amount based on voucher type
        $discount = $discountAmount ?? match ($voucherType) {
            VoucherType::Percentage => $this->percentageRedeemDiscount($voucher, $redeemedBy),
            VoucherType::Fixed => (int) ($voucher->value ?? 0),
            default => 0,
        };
        $money = Money::{$currency}(max(0, $discount));
        $metadata = [
            'order_id' => $orderId,
        ];

        if ($redeemedBy instanceof Model) {
            $orderNumber = $redeemedBy->getAttribute('order_number');

            if (is_string($orderNumber) && mb_trim($orderNumber) !== '') {
                $metadata['order_number'] = mb_trim($orderNumber);
            }

            foreach (['subtotal', 'discount_total', 'grand_total'] as $attribute) {
                $value = $redeemedBy->getAttribute($attribute);

                if (is_numeric($value)) {
                    $metadata[$attribute] = (int) $value;
                }
            }
        }

        $this->recordUsage(
            code: $code,
            discountAmount: $money,
            channel: 'checkout',
            metadata: $metadata,
            redeemedBy: $redeemedBy,
            voucherModel: $voucher
        );
    }

    private function resolveMoneyCurrency(?string $currency, VoucherModel $voucher): string
    {
        $currencies = Currency::getCurrencies();

        foreach ([$currency, $voucher->currency, config('vouchers.default_currency', 'MYR'), 'MYR'] as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $normalized = mb_strtoupper($candidate);

            if (preg_match('/^[A-Z]{3}$/', $normalized) && array_key_exists($normalized, $currencies)) {
                return $normalized;
            }
        }

        $firstAvailable = array_key_first($currencies);

        return is_string($firstAvailable) && $firstAvailable !== '' ? $firstAvailable : 'MYR';
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

        return app(VoucherDiscountCalculator::class)->calculate(VoucherData::fromModel($voucher), (int) $subtotal);
    }

    private function resolveRedeemedByOrder(string $orderId): ?Model
    {
        if (! class_exists(Order::class) || ! Schema::hasTable((new Order)->getTable())) {
            return null;
        }

        return Order::query()
            ->select(['id', 'order_number', 'subtotal', 'discount_total', 'grand_total'])
            ->find($orderId);
    }
}
