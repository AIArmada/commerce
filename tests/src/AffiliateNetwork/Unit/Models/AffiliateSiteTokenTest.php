<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateSite;

describe('site catalog tokens', function (): void {
    test('issuing stores encrypted and returns plaintext once', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['domain' => 'token-issue.example']);

        expect($site->hasCatalogToken())->toBeFalse();

        $token = $site->issueCatalogToken();
        $fresh = $site->fresh();

        expect($token)->toHaveLength(48)
            ->and($fresh->hasCatalogToken())->toBeTrue()
            ->and($fresh->catalog_token_issued_at)->not->toBeNull()
            ->and(decrypt($fresh->catalog_token_encrypted))->toBe($token);
    });

    test('rotation invalidates the previous token', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['domain' => 'token-rotate.example']);

        $first = $site->issueCatalogToken();
        $second = $site->rotateCatalogToken();

        expect($second)->not->toBe($first)
            ->and(decrypt($site->fresh()->catalog_token_encrypted))->toBe($second);
    });
});
