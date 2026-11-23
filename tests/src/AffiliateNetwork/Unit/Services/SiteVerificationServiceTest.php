<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\DnsResolver;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\GenerateVerificationToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySiteByStrategy;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

describe('site verification actions', function (): void {
    test('generateToken creates unique token for site', function (): void {
        $site = AffiliateSite::factory()->pending()->create();
        $token = app(GenerateVerificationToken::class)->execute($site);

        expect($token)->toStartWith('affiliatenetwork-verify-');
        expect(mb_strlen($token))->toBeGreaterThan(30);

        $site->refresh();
        expect($site->verification_token)->toBe($token);
    });

    test('generateToken updates existing token', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'old-token',
        ]);

        $newToken = app(GenerateVerificationToken::class)->execute($site);

        expect($newToken)->not->toBe('old-token');
        expect($site->fresh()->verification_token)->toBe($newToken);
    });

    test('verification fails and does not verify when the DNS strategy finds no token', function (): void {
        app()->instance(DnsResolver::class, new class implements DnsResolver
        {
            public function getTxtRecords(string $hostname): array
            {
                return [];
            }
        });

        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => null,
        ]);

        expect(fn () => app(VerifySiteByStrategy::class)->execute($site, 'dns'))
            ->toThrow(RuntimeException::class);
    });

    test('verify throws for unknown method', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'affiliatenetwork-verify-' . Str::random(32),
        ]);

        expect(fn () => app(VerifySiteByStrategy::class)->execute($site, 'unknown_method'))
            ->toThrow(RuntimeException::class);
    });

    test('verify succeeds for meta tag when token is present in fetched page', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'domain' => 'verified.example',
            'verification_token' => 'affiliatenetwork-verify-' . Str::random(16),
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://verified.example/*' => Http::response("<html><head><meta name=\"affiliate-network-verify\" content=\"{$site->verification_token}\"></head></html>", 200),
            'http://verified.example/*' => Http::response('', 404),
        ]);

        app()->instance(PublicHttpUrlGuard::class, new PublicHttpUrlGuard(
            static fn (string $host): array => $host === 'verified.example' ? ['93.184.216.34'] : [],
        ));

        $verified = app(VerifySiteByStrategy::class)->execute($site, 'meta_tag');

        expect($verified->status)->toBe(SiteStatus::Verified);
        expect($verified->verified_at)->not->toBeNull();
    });

    test('verify refuses localhost-like domains and does not perform HTTP request', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'domain' => 'localhost',
            'verification_token' => 'affiliatenetwork-verify-' . Str::random(16),
        ]);

        Http::fake();

        expect(fn () => app(VerifySiteByStrategy::class)->execute($site, 'file'))
            ->toThrow(RuntimeException::class);

        Http::assertNothingSent();
    });

    test('getInstructions returns DNS instructions', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'test-token-123',
        ]);

        $instructions = app(VerifySiteByStrategy::class)->getInstructions($site, 'dns');

        expect($instructions)->toHaveKey('title');
        expect($instructions)->toHaveKey('record_type', 'TXT');
        expect($instructions)->toHaveKey('record_name', '@');
        expect($instructions)->toHaveKey('record_value', 'test-token-123');
    });

    test('getInstructions returns meta tag instructions', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'test-token-123',
        ]);

        $instructions = app(VerifySiteByStrategy::class)->getInstructions($site, 'meta_tag');

        expect($instructions)->toHaveKey('title');
        expect($instructions)->toHaveKey('html');
        expect($instructions['html'])->toContain('test-token-123');
        expect($instructions['html'])->toContain('affiliate-network-verify');
    });

    test('getInstructions returns file instructions', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'test-token-123',
        ]);

        $instructions = app(VerifySiteByStrategy::class)->getInstructions($site, 'file');

        expect($instructions)->toHaveKey('title');
        expect($instructions)->toHaveKey('path', '/.well-known/affiliate-network-verify.txt');
        expect($instructions)->toHaveKey('content', 'test-token-123');
    });

    test('getInstructions generates token if not set', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => null,
        ]);

        $instructions = app(VerifySiteByStrategy::class)->getInstructions($site, 'dns');

        expect($instructions['record_value'])->toStartWith('affiliatenetwork-verify-');
        expect($site->fresh()->verification_token)->not->toBeNull();
    });

    test('getInstructions returns empty for unknown method', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'verification_token' => 'test-token-123',
        ]);

        expect(fn () => app(VerifySiteByStrategy::class)->getInstructions($site, 'unknown'))
            ->toThrow(RuntimeException::class);
    });
});
