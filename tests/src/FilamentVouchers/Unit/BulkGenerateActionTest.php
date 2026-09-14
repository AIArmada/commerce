<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Actions\BulkGenerateVouchersAction;
use AIArmada\Vouchers\Models\Voucher;

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

it('clamps forged counts instead of generating unbounded batches', function (): void {
    bulkGenerate(bulkPayload(['count' => 5000, 'name' => 'Clamped']));

    expect(Voucher::query()->where('name', 'like', 'Clamped #%')->count())->toBe(100);
});

it('tolerates a missing prefix without crashing', function (): void {
    $payload = bulkPayload(['count' => 1, 'name' => 'NoPrefix']);
    unset($payload['prefix']);

    bulkGenerate($payload);

    expect(Voucher::query()->where('name', 'like', 'NoPrefix #%')->count())->toBe(1);
});
