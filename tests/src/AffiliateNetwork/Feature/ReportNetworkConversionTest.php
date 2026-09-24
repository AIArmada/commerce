<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Http\Controllers\ReportNetworkConversionController;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Http\Request;

function postbackRequest(array $payload, ?string $token): Request
{
    $server = $token !== null ? ['HTTP_AUTHORIZATION' => 'Bearer ' . $token] : [];

    return Request::create('/api/affiliate-network/conversions', 'POST', $payload, [], [], $server);
}

function postbackFixtures(string $domain, int $rateBp = 1500): array
{
    $site = AffiliateSite::factory()->verified()->create([
        'domain' => $domain,
        'catalog_token_encrypted' => encrypt('site-secret'),
    ]);

    $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
        'rate_base_bp' => $rateBp,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'POSTBACK' . uniqid(),
        'name' => 'Postback Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);

    $link = AffiliateOfferLink::factory()
        ->forOffer($offer)
        ->forAffiliateId((string) $affiliate->getKey())
        ->create(['currency' => 'MYR']);

    return [$site, $offer, $affiliate, $link];
}

describe('ReportNetworkConversionController', function (): void {
    test('records counters and ledger conversion for valid site credentials', function (): void {
        [$site, $offer, $affiliate, $link] = postbackFixtures('postback.example');

        $response = app(ReportNetworkConversionController::class)(
            postbackRequest([
                'site' => 'postback.example',
                'link_code' => $link->code,
                'external_reference' => 'ORDER-1',
                'revenue_minor' => 89900,
                'currency' => 'MYR',
            ], 'site-secret'),
            app(OfferLinkService::class)
        );

        expect($response->getStatusCode())->toBe(200);

        $payload = $response->getData(true);

        expect($payload['duplicate'])->toBeFalse()
            ->and($payload['conversion']['commission_minor'])->toBe(13485)
            ->and($payload['conversion']['affiliate_code'])->toBe($affiliate->code)
            ->and($link->fresh()->conversions)->toBe(1)
            ->and($link->fresh()->revenue)->toBe(89900)
            ->and(AffiliateConversion::where('external_reference', 'ORDER-1')->count())->toBe(1);
    });

    test('redeliveries return existing state without touching counters', function (): void {
        [$site, $offer, $affiliate, $link] = postbackFixtures('redeliver.example');

        $makeRequest = static fn (): Request => postbackRequest([
            'site' => 'redeliver.example',
            'link_code' => $link->code,
            'external_reference' => 'ORDER-9',
            'revenue_minor' => 5000,
        ], 'site-secret');

        $controller = app(ReportNetworkConversionController::class);
        $links = app(OfferLinkService::class);

        expect($controller($makeRequest(), $links)->getData(true)['duplicate'])->toBeFalse();
        expect($controller($makeRequest(), $links)->getData(true)['duplicate'])->toBeTrue();

        expect($link->fresh()->conversions)->toBe(1)
            ->and(AffiliateConversion::where('external_reference', 'ORDER-9')->count())->toBe(1);
    });

    test('rejects bad credentials and foreign links', function (): void {
        [$site, $offer, $affiliate, $link] = postbackFixtures('secure.example');

        $controller = app(ReportNetworkConversionController::class);
        $links = app(OfferLinkService::class);

        $payload = [
            'site' => 'secure.example',
            'link_code' => $link->code,
            'external_reference' => 'ORDER-X',
            'revenue_minor' => 100,
        ];

        expect($controller(postbackRequest($payload, 'wrong'), $links)->getStatusCode())->toBe(401);
        expect($controller(postbackRequest($payload, null), $links)->getStatusCode())->toBe(401);

        $other = AffiliateSite::factory()->verified()->create([
            'domain' => 'other.example',
            'catalog_token_encrypted' => encrypt('other-secret'),
        ]);

        $foreign = postbackRequest(array_merge($payload, ['site' => 'other.example']), 'other-secret');

        expect($controller($foreign, $links)->getStatusCode())->toBe(404);
        expect($link->fresh()->conversions)->toBe(0);
    });
});
