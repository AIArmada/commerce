<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\Catalog\RemoteCatalogClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->site = AffiliateSite::create([
        'name' => 'Remote Site',
        'domain' => 'remote-' . uniqid() . '.example.com',
        'status' => AffiliateSite::STATUS_VERIFIED,
        'verified_at' => now(),
        'catalog_url' => 'https://merchant.test/api/affiliates',
    ]);

    // Bypass real DNS: resolve everything to a public TEST-NET IP.
    $this->client = new RemoteCatalogClient(
        new PublicHttpUrlGuard(dnsResolver: fn (string $host): array => ['93.184.216.34']),
    );
});

describe('RemoteCatalogClient', function (): void {
    test('parses catalog snapshot payload', function (): void {
        Http::fake([
            '*' => Http::response([
                'version' => 'v1',
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

        $snapshot = $this->client->snapshot($this->site, 'prog-1');

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

        expect($this->client->programIds($this->site))->toBe(['prog-1', 'prog-2']);
    });

    test('rejects invalid payloads and failed statuses', function (): void {
        Http::fake(['*' => Http::response(['nope' => true])]);

        expect(fn (): array => $this->client->snapshot($this->site, 'prog-1'))
            ->toThrow(OfferNotFoundException::class);

        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        expect(fn (): array => $this->client->snapshot($this->site, 'prog-1'))
            ->toThrow(OfferNotFoundException::class);
    });

    test('requires catalog_url', function (): void {
        $this->site->update(['catalog_url' => null]);

        expect(fn (): array => $this->client->snapshot($this->site, 'prog-1'))
            ->toThrow(OfferNotFoundException::class);
    });
});
