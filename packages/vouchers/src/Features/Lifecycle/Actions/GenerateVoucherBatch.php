<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class GenerateVoucherBatch
{
    public function __construct(private CreateVoucher $createVoucher) {}

    public function handle(int $count, string $prefix, string $name, VoucherType $type, int $value, string $currency, ?int $usageLimit): int
    {
        if ($count < 1 || $count > 100) {
            throw ValidationException::withMessages(['count' => 'Generate between 1 and 100 vouchers per batch.']);
        }

        if (config('vouchers.owner.enabled', false)) {
            OwnerContext::assertResolvedOrExplicitGlobal(OwnerContext::resolve(), 'Bulk voucher generation requires an owner context or explicit global context.');
        }

        $prefix = mb_strtoupper(mb_trim($prefix));

        if ($prefix !== '' && ! preg_match('/^[A-Z0-9_-]{1,10}$/D', $prefix)) {
            throw ValidationException::withMessages(['prefix' => 'Use at most 10 letters, digits, underscores or hyphens.']);
        }

        return (new Voucher)->getConnection()->transaction(function () use ($count, $prefix, $name, $type, $value, $currency, $usageLimit): int {
            for ($sequence = 1; $sequence <= $count; $sequence++) {
                for ($attempt = 1; $attempt <= 5; $attempt++) {
                    try {
                        $this->createVoucher->handle([
                            'code' => ($prefix === '' ? '' : $prefix . '-') . Str::upper(Str::random(6)),
                            'name' => $name . ' #' . $sequence,
                            'type' => $type,
                            'value' => $value,
                            'currency' => $currency,
                            'usage_limit' => $usageLimit,
                        ]);

                        break;
                    } catch (UniqueConstraintViolationException $exception) {
                        if ($attempt === 5) {
                            throw $exception;
                        }
                    }
                }
            }

            return $count;
        });
    }
}
