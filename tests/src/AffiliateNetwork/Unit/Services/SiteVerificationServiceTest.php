<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;
use Illuminate\Support\Str;

describe('SiteVerificationService', function (): void {
    test('generateToken creates unique token for site', function (): void {
        $site = AffiliateSite::factory()->pending()->create();
        $service = app(SiteVerificationService::class);

        $token = $service->generateToken($site);

        expect($token)->toStartWith('affiliatenetwork-verify-');
        expect(mb_strlen($token))->toBeGreaterThan(30);

        $site->refresh();
        expect($site->verification_token)->toBe($token);
    });

    test('generateToken updates existing token', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'old-token',
        ]);
        $service = app(SiteVerificationService::class);

        $newToken = $service->generateToken($site);

        expect($newToken)->not->toBe('old-token');
        expect($site->fresh()->verification_token)->toBe($newToken);
    });

    test('verify returns false when no token set', function (): void {
        $site = AffiliateSite::factory()->create([
            'verification_token' => null,
        ]);
        $service = app(SiteVerificationService::class);

        $result = $service->verify($site, 'dns');

        expect($result)->toBeFalse();
    });

    test('verify returns false for unknown method', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'affiliatenetwork-verify-' . Str::random(32),
        ]);
        $service = app(SiteVerificationService::class);

        $result = $service->verify($site, 'unknown_method');

        expect($result)->toBeFalse();
    });

    test('getInstructions returns DNS instructions', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'test-token-123',
        ]);
        $service = app(SiteVerificationService::class);

        $instructions = $service->getInstructions($site, 'dns');

        expect($instructions)->toHaveKey('title');
        expect($instructions)->toHaveKey('record_type', 'TXT');
        expect($instructions)->toHaveKey('record_name', '@');
        expect($instructions)->toHaveKey('record_value', 'test-token-123');
    });

    test('getInstructions returns meta tag instructions', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'test-token-123',
        ]);
        $service = app(SiteVerificationService::class);

        $instructions = $service->getInstructions($site, 'meta_tag');

        expect($instructions)->toHaveKey('title');
        expect($instructions)->toHaveKey('html');
        expect($instructions['html'])->toContain('test-token-123');
        expect($instructions['html'])->toContain('affiliate-network-verify');
    });

    test('getInstructions returns file instructions', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'test-token-123',
        ]);
        $service = app(SiteVerificationService::class);

        $instructions = $service->getInstructions($site, 'file');

        expect($instructions)->toHaveKey('title');
        expect($instructions)->toHaveKey('path', '/.well-known/affiliate-network-verify.txt');
        expect($instructions)->toHaveKey('content', 'test-token-123');
    });

    test('getInstructions generates token if not set', function (): void {
        $site = AffiliateSite::factory()->create([
            'verification_token' => null,
        ]);
        $service = app(SiteVerificationService::class);

        $instructions = $service->getInstructions($site, 'dns');

        expect($instructions['record_value'])->toStartWith('affiliatenetwork-verify-');
        expect($site->fresh()->verification_token)->not->toBeNull();
    });

    test('getInstructions returns empty for unknown method', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'test-token-123',
        ]);
        $service = app(SiteVerificationService::class);

        $instructions = $service->getInstructions($site, 'unknown');

        expect($instructions)->toBeEmpty();
    });
});
