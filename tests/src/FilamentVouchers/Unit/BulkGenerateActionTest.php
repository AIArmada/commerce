<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Actions\BulkGenerateVouchersAction;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

function bulkGenerate(array $data): void
{
    $handler = BulkGenerateVouchersAction::make()->getActionFunction();
    $handler($data);
}

function bulkPayload(array $overrides = []): array
{
    return array_merge([
        'count' => 3,
        'name' => 'Bulk',
        'type' => 'fixed',
        'value' => '5.00',
        'currency' => 'MYR',
        'prefix' => 'TST',
        'usage_limit' => null,
    ], $overrides);
}

it('creates the requested batch with unique codes', function (): void {
    bulkGenerate(bulkPayload());

    $codes = Voucher::query()->where('name', 'like', 'Bulk #%')->pluck('code');

    expect($codes)->toHaveCount(3)
        ->and($codes->unique())->toHaveCount(3)
        ->and($codes->first())->toStartWith('TST-');
});

it('rejects forged counts without generating vouchers', function (): void {
    expect(fn () => bulkGenerate(bulkPayload(['count' => 5000, 'name' => 'Rejected'])))
        ->toThrow(ValidationException::class);

    expect(Voucher::query()->where('name', 'like', 'Rejected #%')->count())->toBe(0);
});

it('tolerates a missing prefix without crashing', function (): void {
    $payload = bulkPayload(['count' => 1, 'name' => 'NoPrefix']);
    unset($payload['prefix']);

    bulkGenerate($payload);

    expect(Voucher::query()->where('name', 'like', 'NoPrefix #%')->count())->toBe(1);
});

it('rejects fractional counts, invalid voucher types and nonintegral usage limits before creating records', function (array $overrides): void {
    expect(fn () => bulkGenerate(bulkPayload(['name' => 'Invalid Batch', ...$overrides])))
        ->toThrow(ValidationException::class);

    expect(Voucher::query()->where('name', 'like', 'Invalid Batch #%')->exists())->toBeFalse();
})->with([
    'fractional count' => [['count' => '1.5']],
    'fractional usage limit' => [['usage_limit' => '2.5']],
    'zero usage limit' => [['usage_limit' => 0]],
    'unknown type' => [['type' => 'not-a-voucher-type']],
]);
