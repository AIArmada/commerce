<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Runtime\WalletClaim;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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
    public function handle(string $code, Model $holder, ?array $metadata = null, ?string $voucherId = null): VoucherWallet
    {
        $connection = (new VoucherWallet)->getConnection();

        return $connection->transaction(function () use ($code, $holder, $metadata, $voucherId, $connection): VoucherWallet {
            $id = $this->catalog->lockForWalletClaim($code, $voucherId, $holder->getMorphClass(), (string) $holder->getKey());

            $owner = config('vouchers.owner.enabled', false)
                ? OwnerContext::resolveConfigured(VoucherWallet::ownerScopeConfig())
                : null;

            $existing = VoucherWallet::query()->forOwner($owner, false)->where('voucher_id', $id)
                ->where('holder_type', $holder->getMorphClass())
                ->where('holder_id', $holder->getKey())
                ->whereNull('redeemed_at')
                ->first();

            if ($existing instanceof VoucherWallet) {
                return $existing;
            }

            try {
                return $connection->transaction(fn (): VoucherWallet => VoucherWallet::create([
                    'voucher_id' => $id,
                    'holder_type' => $holder->getMorphClass(),
                    'holder_id' => $holder->getKey(),
                    'owner_type' => $owner?->getMorphClass(),
                    'owner_id' => $owner?->getKey(),
                    'claimed_at' => CarbonImmutable::now(),
                    'metadata' => $metadata,
                ]));
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $raced = VoucherWallet::query()->forOwner($owner, false)->where('voucher_id', $id)
                    ->where('holder_type', $holder->getMorphClass())
                    ->where('holder_id', $holder->getKey())
                    ->whereNull('redeemed_at')
                    ->first();

                if (! $raced instanceof VoucherWallet) {
                    throw $exception;
                }

                return $raced;
            }
        });
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
