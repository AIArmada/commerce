<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentVouchers\Actions\BulkGenerateVouchersAction;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\CreateVoucher;
use AIArmada\Vouchers\Models\Voucher;

uses(TestCase::class);

function creationDefaults(array $data): array
{
    $method = new ReflectionMethod(CreateVoucher::class, 'mutateFormDataBeforeCreate');

    return $method->invoke(app(CreateVoucher::class), $data);
}

it('refuses silent global voucher creation without explicit global context', function (): void {
    config()->set('vouchers.owner.enabled', true);
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    expect(fn () => creationDefaults(['code' => 'NCTX-1']))->toThrow(NoCurrentOwnerException::class);
});

it('allows explicit global voucher creation', function (): void {
    config()->set('vouchers.owner.enabled', true);
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $data = OwnerContext::withOwner(null, fn (): array => creationDefaults(['code' => 'GLOB-1']));

    expect($data['owner_type'])->toBeNull()
        ->and($data['owner_id'])->toBeNull();
});

it('refuses silent global bulk generation without explicit global context', function (): void {
    config()->set('vouchers.owner.enabled', true);
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $handler = BulkGenerateVouchersAction::make()->getActionFunction();

    expect(fn () => $handler([
        'count' => 1,
        'name' => 'NoCtx',
        'type' => 'fixed',
        'value' => '5.00',
        'currency' => 'MYR',
        'prefix' => 'NC',
        'usage_limit' => null,
    ]))->toThrow(NoCurrentOwnerException::class);

    $leftovers = OwnerContext::withOwner(null, fn (): int => Voucher::query()->where('name', 'like', 'NoCtx #%')->count());

    expect($leftovers)->toBe(0);
});
