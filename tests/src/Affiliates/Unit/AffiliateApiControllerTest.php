<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Http\Controllers\AffiliateApiController;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\Request;

beforeEach(function (): void {
    OwnerContext::clearOverride();

    $this->affiliate = Affiliate::create([
        'code' => 'API-TEST-'.uniqid(),
        'name' => 'API Test Affiliate',
        'contact_email' => 'api@example.com',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $this->controller = app(AffiliateApiController::class);
});

describe('AffiliateApiController', function (): void {
    test('can be instantiated', function (): void {
        $affiliateService = app(AffiliateService::class);
        $reportService = app(AffiliateReportService::class);

        $controller = new AffiliateApiController(
            $affiliateService,
            $reportService,
        );

        expect($controller)->toBeInstanceOf(AffiliateApiController::class);
    });

    describe('summary', function (): void {
        test('returns affiliate summary', function (): void {
            $response = $this->controller->summary($this->affiliate->code);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data)->toBeArray();
        });

        test('returns 404 for unknown affiliate code', function (): void {
            $response = $this->controller->summary('NONEXISTENT-CODE');

            expect($response->getStatusCode())->toBe(404);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toBe('Affiliate not found');
        });

        test('blocks cross-tenant reads when owner scoping is enabled', function (): void {
            config()->set('affiliates.owner.enabled', true);
            config()->set('affiliates.owner.include_global', false);

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'affiliate-api-owner-a@example.com',
                'password' => 'secret',
            ]);

            $ownerB = User::query()->create([
                'name' => 'Owner B',
                'email' => 'affiliate-api-owner-b@example.com',
                'password' => 'secret',
            ]);

            $affiliateA = Affiliate::create([
                'code' => 'API-OWNER-A-'.uniqid(),
                'name' => 'Affiliate A',
                'contact_email' => 'a@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ]);

            OwnerContext::override($ownerB);

            $response = $this->controller->summary($affiliateA->code);

            expect($response->getStatusCode())->toBe(404);
        });

        test('requires owner context when owner scoping is enabled', function (): void {
            config()->set('affiliates.owner.enabled', true);
            config()->set('affiliates.owner.include_global', false);

            OwnerContext::override(null);

            $response = $this->controller->summary($this->affiliate->code);

            expect($response->getStatusCode())->toBe(400);
            expect(json_decode($response->getContent(), true)['message'])->toBe('Owner context required');
        });
    });

    describe('links', function (): void {
        test('generates affiliate link', function (): void {
            $request = Request::create('/api/affiliates/links', 'POST', [
                'url' => 'https://example.com/products',
            ]);

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('link');
            expect($data['link'])->toContain($this->affiliate->code);
            expect(AffiliateLink::query()->count())->toBe(1);
        });

        test('persists canonical subject fields on generated links', function (): void {
            $request = Request::create('/api/affiliates/links', 'POST', [
                'url' => 'https://example.com/products',
                'subject_type' => 'product',
                'subject_identifier' => 'product:sku-123',
                'subject_instance' => 'web',
                'subject_title_snapshot' => 'SKU 123',
                'subject_metadata' => [
                    'subject_id' => 'sku-123',
                    'category' => 'featured',
                ],
            ]);

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(200);

            $link = AffiliateLink::query()->sole();

            expect($link->subject_type)->toBe('product')
                ->and($link->subject_identifier)->toBe('product:sku-123')
                ->and($link->subject_instance)->toBe('web')
                ->and($link->subject_title_snapshot)->toBe('SKU 123')
                ->and(data_get($link->subject_metadata, 'subject_id'))->toBe('sku-123');
        });

        test('generates link with default URL', function (): void {
            $request = Request::create('/api/affiliates/links', 'POST');

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('link');
        });

        test('generates link with custom params', function (): void {
            $request = Request::create('/api/affiliates/links', 'POST', [
                'url' => 'https://example.com/products',
                'params' => ['campaign' => 'summer'],
            ]);

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data['link'])->toContain('campaign=summer');
        });

        test('returns 404 for unknown affiliate code', function (): void {
            $request = Request::create('/api/affiliates/links', 'POST');

            $response = $this->controller->links('NONEXISTENT-CODE', $request);

            expect($response->getStatusCode())->toBe(404);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toBe('Affiliate not found');
        });

        test('generates link with TTL', function (): void {
            $request = Request::create('/api/affiliates/links', 'POST', [
                'url' => 'https://example.com/products',
                'ttl' => 86400,
            ]);

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('link');
        });

        test('requires owner context when owner scoping is enabled', function (): void {
            config()->set('affiliates.owner.enabled', true);
            config()->set('affiliates.owner.include_global', false);

            OwnerContext::override(null);

            $request = Request::create('/api/affiliates/links', 'POST');

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(400);
            expect(json_decode($response->getContent(), true)['message'])->toBe('Owner context required');
        });
    });

    describe('creatives', function (): void {
        test('returns empty creatives for affiliate without metadata', function (): void {
            $response = $this->controller->creatives($this->affiliate->code);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data['creatives'])->toBeArray();
            expect($data['creatives'])->toBeEmpty();
        });

        test('returns creatives from metadata', function (): void {
            $this->affiliate->update([
                'metadata' => [
                    'creatives' => [
                        ['type' => 'banner', 'url' => 'https://example.com/banner.jpg'],
                        ['type' => 'text', 'content' => 'Best deals!'],
                    ],
                ],
            ]);

            $response = $this->controller->creatives($this->affiliate->code);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data['creatives'])->toHaveCount(2);
            expect($data['creatives'][0]['type'])->toBe('banner');
        });

        test('returns 404 for unknown affiliate code', function (): void {
            $response = $this->controller->creatives('NONEXISTENT-CODE');

            expect($response->getStatusCode())->toBe(404);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toBe('Affiliate not found');
        });

        test('requires owner context when owner scoping is enabled', function (): void {
            config()->set('affiliates.owner.enabled', true);
            config()->set('affiliates.owner.include_global', false);

            OwnerContext::override(null);

            $response = $this->controller->creatives($this->affiliate->code);

            expect($response->getStatusCode())->toBe(400);
            expect(json_decode($response->getContent(), true)['message'])->toBe('Owner context required');
        });
    });
});

describe('AffiliateApiController class structure', function (): void {
    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(AffiliateApiController::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has required public methods', function (): void {
        $reflection = new ReflectionClass(AffiliateApiController::class);

        expect($reflection->hasMethod('summary'))->toBeTrue();
        expect($reflection->hasMethod('links'))->toBeTrue();
        expect($reflection->hasMethod('creatives'))->toBeTrue();
    });

    test('registers link creation as a post route', function (): void {
        $source = file_get_contents('/Users/saiffil/Herd/commerce/packages/affiliates/routes/api.php');

        expect($source)
            ->toContain("Route::post('{code}/links'")
            ->not->toContain("Route::get('{code}/links'");
    });
});
