<?php

declare(strict_types=1);

use AIArmada\Affiliates\Events\AffiliateActivated;
use AIArmada\Affiliates\Events\AffiliateCreated;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleRecord;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReference;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateVoucherIssuer;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateVoucherProvisioning;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active as AffiliateActive;
use AIArmada\Affiliates\States\Draft;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Features\Lifecycle\EntryPoints\AffiliateIntegrationRegistrar;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'vouchers.affiliates.enabled' => true,
        'vouchers.affiliates.auto_create_voucher' => true,
        'vouchers.affiliates.create_on_activation' => true,
        'vouchers.affiliates.set_default_voucher_code' => true,
        'vouchers.affiliates.code_prefix' => 'REF',
        'vouchers.affiliates.code_format' => 'prefix_code',
    ]);

    Event::forget(AffiliateCreated::class);
    Event::forget(AffiliateActivated::class);
});

function voucherIntegrationAffiliate(string $status = AffiliateActive::class): Affiliate
{
    return Affiliate::create([
        'code' => 'PARTNER' . mb_strtoupper(Str::random(8)),
        'name' => 'Partner Referral Affiliate',
        'status' => $status,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
}

it('does not register listeners when affiliate integration is disabled', function (): void {
    config(['vouchers.affiliates.enabled' => false]);
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldNotReceive('listen');

    (new AffiliateIntegrationRegistrar($dispatcher))->register();
});

it('does not register lifecycle listeners when both switches are disabled', function (): void {
    config([
        'vouchers.affiliates.auto_create_voucher' => false,
        'vouchers.affiliates.create_on_activation' => false,
    ]);

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldNotReceive('listen');

    (new AffiliateIntegrationRegistrar($dispatcher))->register();
});

it('issues one voucher through the real lifecycle events and persists the default code', function (): void {
    app(AffiliateIntegrationRegistrar::class)->register();
    $affiliate = voucherIntegrationAffiliate();

    event(new AffiliateCreated($affiliate->lifecycleReference()));
    event(new AffiliateActivated($affiliate->lifecycleReference()));

    $voucher = Voucher::query()->where('affiliate_id', $affiliate->id)->sole();

    expect($voucher->code)->toBe('REF' . $affiliate->code)
        ->and($voucher->type->value)->toBe('percentage')
        ->and($voucher->affiliate_id)->toBe($affiliate->id)
        ->and($voucher->metadata['auto_generated'])->toBeTrue()
        ->and($voucher->metadata)->not()->toHaveKey('affiliate_code')
        ->and($voucher->metadata)->not()->toHaveKey('affiliate_id')
        ->and($affiliate->fresh()->default_voucher_code)->toBe($voucher->code);
});

it('issues an activation voucher only for an active affiliate', function (): void {
    config(['vouchers.affiliates.auto_create_voucher' => false]);
    app(AffiliateIntegrationRegistrar::class)->register();

    $affiliate = voucherIntegrationAffiliate(Draft::class);
    event(new AffiliateActivated($affiliate->lifecycleReference()));

    expect(Voucher::query()->where('affiliate_id', $affiliate->id)->exists())->toBeFalse();

    $affiliate->update(['status' => AffiliateActive::class]);

    expect(Voucher::query()->where('affiliate_id', $affiliate->id)->count())->toBe(1);
});

it('provisions the lifecycle voucher only after the affiliate transaction commits', function (): void {
    app(AffiliateIntegrationRegistrar::class)->register();

    $affiliateId = null;

    DB::transaction(function () use (&$affiliateId): void {
        $affiliateId = voucherIntegrationAffiliate()->id;

        expect(Voucher::query()->where('affiliate_id', $affiliateId)->exists())->toBeFalse();
    });

    expect(Voucher::query()->where('affiliate_id', $affiliateId)->count())->toBe(1);
});

it('provisions the activation voucher only after the affiliate transaction commits', function (): void {
    config(['vouchers.affiliates.auto_create_voucher' => false]);
    app(AffiliateIntegrationRegistrar::class)->register();

    $affiliate = voucherIntegrationAffiliate(Draft::class);

    DB::transaction(function () use ($affiliate): void {
        $affiliate->update(['status' => AffiliateActive::class]);

        expect(Voucher::query()->where('affiliate_id', $affiliate->id)->exists())->toBeFalse();
    });

    expect(Voucher::query()->where('affiliate_id', $affiliate->id)->count())->toBe(1);
});

it('does not provision a voucher when the affiliate transaction rolls back', function (): void {
    app(AffiliateIntegrationRegistrar::class)->register();

    expect(fn () => DB::transaction(function (): void {
        voucherIntegrationAffiliate();

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);

    expect(Voucher::query()->count())->toBe(0);
});

it('supports code formats without updating the default when disabled', function (string $format): void {
    config([
        'vouchers.affiliates.code_format' => $format,
        'vouchers.affiliates.set_default_voucher_code' => false,
    ]);
    app(AffiliateIntegrationRegistrar::class)->register();

    $affiliate = voucherIntegrationAffiliate();
    $voucher = Voucher::query()->where('affiliate_id', $affiliate->id)->sole();

    match ($format) {
        'code_only' => expect($voucher->code)->toBe($affiliate->code),
        'prefix_random' => expect($voucher->code)->toStartWith('REF')->and(mb_strlen($voucher->code))->toBe(11),
        default => expect($voucher->code)->toBe('REF' . $affiliate->code),
    };

    expect($affiliate->fresh()->default_voucher_code)->toBeNull();
})->with(['prefix_code', 'code_only', 'prefix_random', 'unknown_format']);

it('rejects forged lifecycle references without issuing a voucher', function (): void {
    $affiliate = voucherIntegrationAffiliate();
    app(AffiliateIntegrationRegistrar::class)->register();

    $reference = new AffiliateLifecycleReference($affiliate->id, $affiliate->owner_type, (string) Str::uuid());

    expect(fn () => event(new AffiliateCreated($reference)))
        ->toThrow(InvalidArgumentException::class, 'does not match the canonical source');

    expect(Voucher::query()->where('affiliate_id', $affiliate->id)->exists())->toBeFalse();
});

it('rejects voucher issuers on another database connection before they can write', function (): void {
    $affiliate = voucherIntegrationAffiliate();

    $issuer = Mockery::mock(AffiliateVoucherIssuer::class);
    $issuer->shouldReceive('connectionName')->once()->andReturn('another-connection');
    $issuer->shouldNotReceive('issue');

    expect(fn () => app(AffiliateVoucherProvisioning::class)->provision($affiliate->lifecycleReference(), $issuer, false, true))
        ->toThrow(LogicException::class, 'shared database connection');
});

it('rolls back voucher issuance when the issuer fails before returning the code', function (): void {
    $affiliate = voucherIntegrationAffiliate();

    $issuer = Mockery::mock(AffiliateVoucherIssuer::class);
    $issuer->shouldReceive('connectionName')->andReturn((string) (new Voucher)->getConnection()->getName());
    $issuer->shouldReceive('issue')->once()->andReturnUsing(function (AffiliateLifecycleRecord $record): never {
        Voucher::create([
            'code' => 'ROLLBACK' . $record->code,
            'name' => 'Rolled back voucher',
            'type' => 'percentage',
            'value' => 1000,
            'currency' => 'MYR',
            'status' => 'active',
            'affiliate_id' => $record->reference->id,
        ]);

        throw new RuntimeException('Issuer failure');
    });

    expect(fn () => app(AffiliateVoucherProvisioning::class)->provision($affiliate->lifecycleReference(), $issuer, false, true))
        ->toThrow(RuntimeException::class, 'Issuer failure');

    expect(Voucher::query()->where('affiliate_id', $affiliate->id)->exists())->toBeFalse()
        ->and($affiliate->fresh()->default_voucher_code)->toBeNull();
});

afterEach(function (): void {
    Mockery::close();
});
