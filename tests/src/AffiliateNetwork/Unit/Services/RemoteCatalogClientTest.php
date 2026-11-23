<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\SiteCatalogEndpoint;
use AIArmada\AffiliateNetwork\Drivers\RemoteCatalogHttpDriver;
use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->site = AffiliateSite::factory()->verified()->create([
        'name' => 'Remote Site',
        'domain' => 'remote-' . uniqid() . '.example.com',
        'catalog_url' => 'https://merchant.test/api/affiliates',
    ]);

    $this->endpoint = new SiteCatalogEndpoint(
        siteId: (string) $this->site->getKey(),
        domain: (string) $this->site->domain,
        catalogUrl: $this->site->catalog_url,
        catalogTokenEncrypted: $this->site->catalog_token_encrypted,
    );

    $this->client = new RemoteCatalogHttpDriver(
        new PublicHttpUrlGuard(dnsResolver: fn (string $host): array => ['93.184.216.34']),
    );
});

describe('RemoteCatalogHttpDriver', function (): void {
    test('parses catalog snapshot payload', function (): void {
        Http::fake([
            '*' => Http::response([
                'version' => 'v2',
                'creatives' => [],
                'program_id' => 'prog-1',
                'currency' => 'MYR',
                'cookie_days' => 30,
                'base' => ['commission_type' => 'percentage', 'default_rate_bp' => 1000],
                'subjects' => [[
                    'subject_type' => 'product',
                    'subject_key' => 'SKU-1',
                    'title' => 'Widget',
                    'url' => 'https://merchant.test/x/sku-1',
                    'effective' => ['commission_type' => 'percentage', 'rate_bp' => 1500, 'applied_rule_ids' => ['r1']],
                ]],
                'variable_extras' => ['volume_tiers' => [], 'promotions' => []],
            ]),
        ]);

        $snapshot = $this->client->snapshot($this->endpoint, 'prog-1');

        expect($snapshot['program_id'])->toBe('prog-1');
        expect($snapshot['subjects'][0]['effective']['rate_bp'])->toBe(1500);
    });

    test('lists program ids from index payload', function (): void {
        Http::fake([
            '*' => Http::response(['data' => [
                ['program_id' => 'prog-1', 'name' => 'One'],
                ['program_id' => 'prog-2', 'name' => 'Two'],
                ['name' => 'Missing id'],
            ]]),
        ]);

        expect($this->client->programIds($this->endpoint))->toBe(['prog-1', 'prog-2']);
    });

    test('rejects invalid payloads and failed statuses', function (): void {
        Http::fake(['*' => Http::response(['nope' => true])]);

        expect(fn (): array => $this->client->snapshot($this->endpoint, 'prog-1'))
            ->toThrow(OfferNotFoundException::class);

        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        expect(fn (): array => $this->client->snapshot($this->endpoint, 'prog-1'))
            ->toThrow(OfferNotFoundException::class);
    });

    test('requires catalog_url', function (): void {
        $this->site->update(['catalog_url' => null]);

        $endpoint = new SiteCatalogEndpoint(
            siteId: (string) $this->site->getKey(),
            domain: (string) $this->site->domain,
            catalogUrl: null,
            catalogTokenEncrypted: null,
        );

        expect(fn (): array => $this->client->snapshot($endpoint, 'prog-1'))
            ->toThrow(OfferNotFoundException::class);
    });

    test('rejects catalog responses larger than the configured cap', function (): void {
        config(['affiliate-network.http.max_response_bytes' => 8]);
        Http::fake(['*' => Http::response('123456789', 200)]);

        expect(fn (): array => $this->client->snapshot($this->endpoint, 'prog-1'))
            ->toThrow(OfferNotFoundException::class, 'exceeded the configured size limit');
    });

    test('fails closed when the catalog token cannot be decrypted', function (): void {
        Http::fake();

        $endpoint = new SiteCatalogEndpoint(
            siteId: (string) $this->site->getKey(),
            domain: (string) $this->site->domain,
            catalogUrl: $this->site->catalog_url,
            catalogTokenEncrypted: 'not-a-valid-ciphertext',
        );

        expect(fn (): array => $this->client->snapshot($endpoint, 'prog-1'))
            ->toThrow(OfferNotFoundException::class, 'cannot be decrypted');

        Http::assertNothingSent();
    });

    test('encodes program ids placed into the request path', function (): void {
        Http::fake(['*' => Http::response([
            'version' => 'v2',
            'creatives' => [],
            'program_id' => 'a/b?c=d',
            'subjects' => [],
        ])]);

        $this->client->snapshot($this->endpoint, 'a/b?c=d');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'programs/a%2Fb%3Fc%3Dd/catalog'));
    });
});

test('remote catalogs require version two and a complete creative list', function (): void {
    foreach ([
        ['version' => 'v1', 'program_id' => 'prog-1', 'subjects' => [], 'creatives' => []],
        ['version' => 'v2', 'program_id' => 'prog-1', 'subjects' => []],
        ['version' => 'v2', 'program_id' => 'prog-1', 'subjects' => [], 'creatives' => null],
    ] as $payload) {
        Http::fake(['*' => Http::response($payload)]);
        expect(fn () => $this->client->snapshot($this->endpoint, 'prog-1'))->toThrow(OfferNotFoundException::class);
    }
});
