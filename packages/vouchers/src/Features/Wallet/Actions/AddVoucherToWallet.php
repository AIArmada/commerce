<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Concerns\ResolvesVoucherAssignee;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
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
    use QueriesVouchers;
    use ResolvesVoucherAssignee;

    /**
     * Add a voucher to the owner's wallet.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(string | VoucherModel $code, Model $holder, ?array $metadata = null): VoucherWallet
    {
        return (new VoucherModel)->getConnection()->transaction(function () use ($code, $holder, $metadata): VoucherWallet {
            $query = $this->voucherQuery();

            if ($code instanceof VoucherModel) {
                $query->whereKey($code->getKey());
            } else {
                $query->where('code', $this->normalizeCode($code));
            }

            $voucher = $query->lockForUpdate()->first();

            if (! $voucher instanceof VoucherModel) {
                throw VoucherNotFoundException::withCode($code instanceof VoucherModel ? $code->code : $code);
            }

            $assignee = $this->resolveAssignee($voucher);

            if ($assignee !== null && ($holder->getMorphClass() !== $assignee['type'] || (string) $holder->getKey() !== $assignee['id'])) {
                throw InvalidVoucherException::assignedToAnother($voucher->code);
            }

            $owner = config('vouchers.owner.enabled', false)
                ? OwnerContext::resolveConfigured(VoucherWallet::ownerScopeConfig())
                : null;

            $existing = VoucherWallet::query()->forOwner($owner, false)->where('voucher_id', $voucher->id)
                ->where('holder_type', $holder->getMorphClass())
                ->where('holder_id', $holder->getKey())
                ->whereNull('redeemed_at')
                ->first();

            if ($existing instanceof VoucherWallet) {
                return $existing;
            }

            try {
                return $voucher->getConnection()->transaction(fn (): VoucherWallet => VoucherWallet::create([
                    'voucher_id' => $voucher->id,
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

                $raced = VoucherWallet::query()->forOwner($owner, false)->where('voucher_id', $voucher->id)
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
