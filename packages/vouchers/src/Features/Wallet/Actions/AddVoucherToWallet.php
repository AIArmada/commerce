<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Data\WalletClaimData;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Features\Wallet\Api\WalletClaim;
use AIArmada\Vouchers\Models\VoucherWallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Add a voucher to the owner's wallet.
 */
final class AddVoucherToWallet implements WalletClaim
{
    use AsAction;

    public function __construct(private readonly VoucherCatalog $catalog) {}

    /**
     * Add a voucher to the owner's wallet.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(string $code, RecordReference $holder, ?array $metadata = null, ?string $voucherId = null): WalletClaimData
    {
        $connection = (new VoucherWallet)->getConnection();

        if ($connection->getName() !== $this->catalog->connectionName()) {
            throw new LogicException('Wallet claims require a shared voucher database connection.');
        }

        return $connection->transaction(function () use ($code, $holder, $metadata, $voucherId, $connection): WalletClaimData {
            $id = $this->catalog->lockForWalletClaim($code, $voucherId, $holder->type, $holder->id);

            $owner = config('vouchers.owner.enabled', false)
                ? OwnerContext::resolveConfigured(VoucherWallet::ownerScopeConfig())
                : null;

            $existing = VoucherWallet::query()->forOwner($owner, false)->where('voucher_id', $id)
                ->where('holder_type', $holder->type)
                ->where('holder_id', $holder->id)
                ->whereNull('redeemed_at')
                ->first();

            if ($existing instanceof VoucherWallet) {
                return $this->snapshot($existing, $holder, false);
            }

            try {
                $wallet = $connection->transaction(fn (): VoucherWallet => VoucherWallet::create([
                    'voucher_id' => $id,
                    'holder_type' => $holder->type,
                    'holder_id' => $holder->id,
                    'owner_type' => $owner?->getMorphClass(),
                    'owner_id' => $owner?->getKey(),
                    'claimed_at' => CarbonImmutable::now(),
                    'metadata' => $metadata,
                ]));

                return $this->snapshot($wallet, $holder, true);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $raced = VoucherWallet::query()->forOwner($owner, false)->where('voucher_id', $id)
                    ->where('holder_type', $holder->type)
                    ->where('holder_id', $holder->id)
                    ->whereNull('redeemed_at')
                    ->first();

                if (! $raced instanceof VoucherWallet) {
                    throw $exception;
                }

                return $this->snapshot($raced, $holder, false);
            }
        });
    }

    private function snapshot(VoucherWallet $wallet, RecordReference $holder, bool $created): WalletClaimData
    {
        return new WalletClaimData(
            id: $wallet->id,
            voucherId: $wallet->voucher_id,
            holderType: $holder->type,
            holderId: $holder->id,
            metadata: $wallet->metadata,
            created: $created,
        );
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
