<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\AdjustConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\AdjustNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\ReportNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\VoidNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\LedgerReconciliationQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function bugFixSite(string $domain): AffiliateSite
{
    return AffiliateSite::factory()->verified()->create([
        'domain' => $domain,
        'catalog_token_encrypted' => encrypt('site-secret'),
    ]);
}

function bugFixAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'BUGCONV' . uniqid(),
        'handle' => 'bugfix-' . uniqid(),
        'name' => 'Bug Fix Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function bugFixOffer(AffiliateSite $site, array $attributes = []): AffiliateOffer
{
    return AffiliateOffer::factory()->published()->forSite($site)->create(array_merge([
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
        'requires_approval' => false,
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $payload
 */
function bugFixReport(array $payload): JsonResponse
{
    $payload = array_merge([
        'engine_touch_at' => null,
        'network_clicked_at' => CarbonImmutable::now()->subMinute()->toIso8601String(),
    ], $payload);

    return app()->call(ReportNetworkConversionController::class, [
        'request' => Request::create('/api/affiliate-network/conversions', 'POST', $payload, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer site-secret',
        ]),
    ]);
}

function bugFixVoid(string $domain, string $linkCode, string $reference): JsonResponse
{
    return app()->call(VoidNetworkConversionController::class, [
        'request' => Request::create('/api/affiliate-network/conversions/void', 'POST', [
            'site' => $domain,
            'link_code' => $linkCode,
            'external_reference' => $reference,
            'reason' => 'refund',
        ], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer site-secret',
        ]),
    ]);
}

function bugFixRegisterPostbackRoutes(): void
{
    Route::middleware([])
        ->prefix('api/affiliate-network')
        ->group(function (): void {
            Route::post('/conversions', ReportNetworkConversionController::class);
            Route::post('/conversions/void', VoidNetworkConversionController::class);
            Route::post('/conversions/adjust', AdjustNetworkConversionController::class);
        });
}

describe('bug fix regressions', function (): void {
    test('voiding a pending leg reverses it and repeat voids are idempotent', function (): void {
        $site = bugFixSite('pending-void.example');
        $offer = bugFixOffer($site, ['rate_base_bp' => 1500, 'locking_days' => 7]);
        $affiliate = bugFixAffiliate();
        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $affiliate->getKey())
            ->create(['currency' => 'MYR']);

        $reported = bugFixReport([
            'site' => 'pending-void.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'PENDING-LEG-1',
            'revenue_minor' => 89900,
            'currency' => 'MYR',
        ]);

        expect($reported->getStatusCode())->toBe(200)
            ->and($reported->getData(true)['conversion']['status'])->toBe(LegStatus::Pending->value);

        $first = bugFixVoid('pending-void.example', $link->link->slug, 'PENDING-LEG-1');

        expect($first->getStatusCode())->toBe(200)
            ->and($first->getData(true)['duplicate'])->toBeFalse()
            ->and($first->getData(true)['conversion']['status'])->toBe(LegStatus::Reversed->value)
            ->and(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(2);

        $second = bugFixVoid('pending-void.example', $link->link->slug, 'PENDING-LEG-1');

        expect($second->getStatusCode())->toBe(200)
            ->and($second->getData(true)['duplicate'])->toBeTrue()
            ->and($second->getData(true)['conversion']['status'])->toBe(LegStatus::Reversed->value)
            ->and(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(2);
    });

    test('create and update offers coerce a null locking window to zero', function (): void {
        $site = bugFixSite('locking-null.example');

        $offer = app(CreateOffer::class)->execute($site->toRecord(), [
            'name' => 'Locking Null Offer',
            'locking_days' => null,
        ]);

        expect((int) $offer->locking_days)->toBe(0);

        $updated = app(UpdateOffer::class)->execute($offer, [
            'locking_days' => null,
        ]);

        expect((int) $updated->locking_days)->toBe(0);
    });

    test('reconciliation counts primaries once and folds adjustments after a void', function (): void {
        $site = bugFixSite('reconcile-mixed.example');
        $offer = bugFixOffer($site, ['rate_base_bp' => 1000, 'network_fee_bp' => 0]);
        $affiliate = bugFixAffiliate();
        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $affiliate->getKey())
            ->create(['currency' => 'MYR']);

        $record = app(RecordNetworkConversion::class);

        foreach (['A', 'B'] as $reference) {
            $record->execute(
                offerId: (string) $offer->getKey(),
                affiliateId: (string) $affiliate->getKey(),
                revenueMinor: 10000,
                currency: 'MYR',
                externalReference: 'RECON-MIXED-' . $reference,
                linkId: (string) $link->getKey(),
            );
        }

        $legA = NetworkConversionLeg::query()->where('external_reference', 'RECON-MIXED-A')->firstOrFail();
        $legB = NetworkConversionLeg::query()->where('external_reference', 'RECON-MIXED-B')->firstOrFail();

        app(AdjustConversionLeg::class)->execute((string) $legA->getKey(), 'ADJ-1', 5000, 'price correction');
        app(ReverseConversionLeg::class)->execute((string) $legB->getKey(), 'refund');

        $query = new LedgerReconciliationQuery(app(AttributionApi::class), null);
        $report = $query->reconcileLink((string) $link->getKey());

        expect($report['match'])->toBeTrue()
            ->and($report['network']['conversions'])->toBe(1)
            ->and($report['network']['revenue_minor'])->toBe(15000)
            ->and($report['legs']['total'])->toBe(4);

        expect($query->computeCounters((string) $link->getKey()))
            ->toBe(['conversions' => 1, 'revenue_minor' => 15000]);
    });

    test('distinct coupons on one site keep separate idempotency keys', function (): void {
        $site = bugFixSite('coupon-key.example');
        $offer = bugFixOffer($site, ['rate_base_bp' => 1000]);
        $affiliate = bugFixAffiliate();

        $couponA = AffiliateOfferCoupon::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $affiliate->getKey())
            ->withCode('SAVE10A')
            ->create();
        $couponB = AffiliateOfferCoupon::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $affiliate->getKey())
            ->withCode('SAVE10B')
            ->create();

        $base = [
            'site' => 'coupon-key.example',
            'external_reference' => 'COUPON-DUP-REF',
            'revenue_minor' => 10000,
            'currency' => 'MYR',
        ];

        $first = bugFixReport(array_merge($base, ['coupon_code' => 'SAVE10A']));
        $second = bugFixReport(array_merge($base, ['coupon_code' => 'SAVE10B']));

        expect($first->getStatusCode())->toBe(200)
            ->and($first->getData(true)['duplicate'])->toBeFalse()
            ->and($second->getStatusCode())->toBe(200)
            ->and($second->getData(true)['duplicate'])->toBeFalse();

        $keys = NetworkConversionLeg::query()->pluck('idempotency_key')->all();

        expect($keys)->toHaveCount(2)
            ->and($keys)->toContain('leg:coupon:' . $couponA->id . ':COUPON-DUP-REF:purchase')
            ->and($keys)->toContain('leg:coupon:' . $couponB->id . ':COUPON-DUP-REF:purchase');

        expect(bugFixReport(array_merge($base, ['coupon_code' => 'SAVE10A']))->getData(true)['duplicate'])->toBeTrue()
            ->and(bugFixReport(array_merge($base, ['coupon_code' => 'SAVE10B']))->getData(true)['duplicate'])->toBeTrue();
    });

    test('postback endpoints authenticate before validating the payload', function (): void {
        bugFixRegisterPostbackRoutes();
        bugFixSite('auth-first.example');

        $paths = [
            '/api/affiliate-network/conversions',
            '/api/affiliate-network/conversions/void',
            '/api/affiliate-network/conversions/adjust',
        ];

        foreach ($paths as $path) {
            $this->postJson($path, [])->assertStatus(401);
            $this->postJson($path, ['site' => 'auth-first.example'])->assertStatus(401);
            $this->postJson($path, ['site' => 'unknown.example', 'link_code' => 'missing'])->assertStatus(401);
        }

        foreach ($paths as $path) {
            $this->withToken('site-secret')
                ->postJson($path, ['site' => 'auth-first.example'])
                ->assertStatus(422);
        }
    });

    test('deleting an offer cascades offer links, tracked links and clicks', function (): void {
        $site = bugFixSite('delete-cascade.example');
        $offer = bugFixOffer($site);
        $affiliate = bugFixAffiliate();
        $offerLink = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $affiliate->getKey())
            ->create(['currency' => 'MYR']);

        $tracked = $offerLink->link()->withoutOwnerScope()->firstOrFail();
        $click = LinkClick::create([
            'link_id' => $tracked->getKey(),
            'occurred_at' => CarbonImmutable::now(),
        ]);

        expect(AffiliateOfferLink::query()->withoutGlobalScopes()->where('offer_id', $offer->getKey())->exists())->toBeTrue();

        $offer->delete();

        expect(AffiliateOfferLink::query()->withoutGlobalScopes()->where('offer_id', $offer->getKey())->exists())->toBeFalse()
            ->and(Link::query()->withoutOwnerScope()->whereKey($tracked->getKey())->exists())->toBeFalse()
            ->and(LinkClick::query()->withoutOwnerScope()->whereKey($click->getKey())->exists())->toBeFalse();
    });
});
