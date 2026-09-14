<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Actions\Payouts\UpdatePayoutStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateWebhookDelivery;
use AIArmada\Affiliates\Services\CommissionCalculator;
use AIArmada\Affiliates\Services\Commissions\CommissionCaps;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', false);

    $this->affiliate = Affiliate::create([
        'code' => 'REG-' . uniqid(),
        'name' => 'Regression Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

function makeApprovedConversionFor(Affiliate $affiliate, int $commissionMinor): AffiliateConversion
{
    return AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'conversion_type' => 'purchase',
        'commission_minor' => $commissionMinor,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);
}

function seedBalanceFor(Affiliate $affiliate, int $availableMinor): AffiliateBalance
{
    return AffiliateBalance::create([
        'affiliate_id' => $affiliate->id,
        'currency' => 'USD',
        'available_minor' => $availableMinor,
        'holding_minor' => 0,
        'lifetime_earnings_minor' => $availableMinor,
        'minimum_payout_minor' => 100,
    ]);
}

test('commission caps clamp negative and out-of-range commissions', function (): void {
    config()->set('affiliates.commissions.minimum_minor', 100);
    config()->set('affiliates.commissions.maximum_minor', 500);

    expect(CommissionCaps::clamp(-50))->toBe(100)
        ->and(CommissionCaps::clamp(50))->toBe(100)
        ->and(CommissionCaps::clamp(200))->toBe(200)
        ->and(CommissionCaps::clamp(600))->toBe(500);
});

test('commission calculator honors caps on fixed and percentage paths', function (): void {
    config()->set('affiliates.commissions.minimum_minor', 0);
    config()->set('affiliates.commissions.maximum_minor', 500);

    $fixed = Affiliate::create([
        'code' => 'REG-FIXED-' . uniqid(),
        'name' => 'Fixed Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Fixed,
        'commission_rate' => 800,
        'currency' => 'USD',
    ]);

    expect(app(CommissionCalculator::class)->calculate($fixed, 10000))->toBe(500)
        ->and(app(CommissionCalculator::class)->calculate($this->affiliate, 100000))->toBe(500);
});

test('create payout rejects non-approved conversions', function (): void {
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'conversion_type' => 'purchase',
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now(),
    ]);

    expect(fn () => app(CreatePayout::class)->handle([$conversion->id]))
        ->toThrow(InvalidArgumentException::class, 'approved');
});

test('create payout reserves available balance for approved conversions', function (): void {
    seedBalanceFor($this->affiliate, 5000);

    $conversion = makeApprovedConversionFor($this->affiliate, 2000);

    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    expect($payout->total_minor)->toBe(2000)
        ->and($this->affiliate->balance()->first()->available_minor)->toBe(3000)
        ->and($conversion->fresh()->affiliate_payout_id)->toBe($payout->getKey());
});

test('create payout rejects terminal initial status', function (): void {
    seedBalanceFor($this->affiliate, 5000);

    $conversion = makeApprovedConversionFor($this->affiliate, 2000);

    expect(fn () => app(CreatePayout::class)->handle([$conversion->id], ['status' => CompletedPayout::class]))
        ->toThrow(InvalidArgumentException::class, 'pending or processing');
});

test('payout completion marks conversions paid', function (): void {
    seedBalanceFor($this->affiliate, 5000);

    $conversion = makeApprovedConversionFor($this->affiliate, 2000);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    $completed = app(UpdatePayoutStatus::class)->handle($payout, CompletedPayout::class);

    expect($completed->status)->toBeInstanceOf(CompletedPayout::class)
        ->and($completed->paid_at)->not->toBeNull()
        ->and(AffiliateConversion::query()->find($conversion->id)->status)->toBeInstanceOf(PaidConversion::class);
});

test('payout rejects illegal status transitions', function (): void {
    seedBalanceFor($this->affiliate, 5000);

    $conversion = makeApprovedConversionFor($this->affiliate, 2000);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);
    $completed = app(UpdatePayoutStatus::class)->handle($payout, CompletedPayout::class);

    expect(fn () => app(UpdatePayoutStatus::class)->handle($completed, PendingPayout::class))
        ->toThrow(TransitionNotFound::class);
});

test('payout cancellation refunds balance and unlinks conversions', function (): void {
    seedBalanceFor($this->affiliate, 5000);

    $conversion = makeApprovedConversionFor($this->affiliate, 2000);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    app(UpdatePayoutStatus::class)->handle($payout, CancelledPayout::class);

    expect($this->affiliate->balance()->first()->available_minor)->toBe(5000)
        ->and(AffiliateConversion::query()->find($conversion->id)->affiliate_payout_id)->toBeNull()
        ->and(AffiliateConversion::query()->find($conversion->id)->status)->toBeInstanceOf(ApprovedConversion::class);
});

test('owner tuple is not mass assignable', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'REG-SPOOF-' . uniqid(),
        'name' => 'Spoofed Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
        'owner_type' => 'Evil',
        'owner_id' => 'evil-1',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'conversion_type' => 'purchase',
        'commission_minor' => 100,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now(),
        'owner_type' => 'Evil',
        'owner_id' => 'evil-1',
    ]);

    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'cart_instance' => 'default',
        'owner_type' => 'Evil',
        'owner_id' => 'evil-1',
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAY-SPOOF-' . uniqid(),
        'status' => PendingPayout::class,
        'total_minor' => 100,
        'conversion_count' => 1,
        'currency' => 'USD',
        'payee_type' => Affiliate::class,
        'payee_id' => $this->affiliate->id,
        'owner_type' => 'Evil',
        'owner_id' => 'evil-1',
    ]);

    expect($affiliate->owner_type)->toBeNull()
        ->and($affiliate->owner_id)->toBeNull()
        ->and($conversion->owner_type)->toBeNull()
        ->and($conversion->owner_id)->toBeNull()
        ->and($attribution->owner_type)->toBeNull()
        ->and($attribution->owner_id)->toBeNull()
        ->and($payout->owner_type)->toBeNull()
        ->and($payout->owner_id)->toBeNull();
});

test('link verification rejects array signatures without error', function (): void {
    config()->set('affiliates.links.allowed_hosts', ['shop.test']);

    $generator = new AffiliateLinkGenerator;

    expect($generator->verify('https://shop.test/p?aff_sig[]=x&aff_exp=9999999999'))->toBeFalse();
});

test('link verification rejects tampered parameters', function (): void {
    config()->set('affiliates.links.allowed_hosts', ['shop.test']);

    $generator = new AffiliateLinkGenerator;
    $url = $generator->generate('AFF1', 'https://shop.test/p', ['utm' => 'a']);

    expect($generator->verify($url))->toBeTrue()
        ->and($generator->verify($url . '&injected=1'))->toBeFalse();
});

test('webhook dispatcher sends nothing without a signing secret', function (): void {
    config()->set('affiliates.events.dispatch_webhooks', true);
    config()->set('affiliates.webhooks.signature_secret', '');
    config()->set('affiliates.webhooks.endpoints.conversion', ['https://example.com/hook']);

    app(WebhookDispatcher::class)->dispatch('conversion', ['id' => '1']);

    expect(AffiliateWebhookDelivery::query()->count())->toBe(0);
});
