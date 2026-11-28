<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Services;

use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherUsageData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherReservations;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherValidation;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCreator;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherDeletion;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherUpdater;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherUsageHistory;
use AIArmada\Vouchers\Features\Wallet\Api\WalletRemoval;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Runtime\VoucherRedemption;
use AIArmada\Vouchers\Runtime\VoucherServiceInterface;
use AIArmada\Vouchers\Runtime\VoucherUsageRecorder;
use AIArmada\Vouchers\Runtime\WalletClaim;
use AIArmada\Vouchers\States\Active;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;

class VoucherService implements VoucherServiceInterface
{
    use NormalizesVoucherCodes;

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

        return app(VoucherCreator::class)->handle($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $code, array $data): VoucherData
    {
        $voucherId = $this->catalog->resolveId($code);

        if ($voucherId === null) {
            throw VoucherNotFoundException::withCode($code);
        }

        return app(VoucherUpdater::class)->handle($voucherId, $data);
    }

    public function delete(string $code): bool
    {
        $voucherId = $this->catalog->resolveId($code);

        return $voucherId !== null && app(VoucherDeletion::class)->handle($voucherId);
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
        app(VoucherRedemption::class)->redeemManually($code, $discountAmount, $reference, $metadata, $redeemedBy, $notes);
    }

    /**
     * @return list<VoucherUsageData>
     */
    public function getUsageHistory(string $code, int $limit = 100): array
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
        return app(WalletRemoval::class)->handle(
            $code,
            $holder->getMorphClass(),
            $holder->exists ? (string) $holder->getKey() : '',
        );
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

    public function redeem(
        string $code,
        string $orderId,
        ?int $discountAmount = null,
        ?string $currency = null,
        ?string $voucherId = null,
    ): void {
        app(VoucherRedemption::class)->redeem($code, $orderId, $discountAmount, $currency, $voucherId);
    }
}
