<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Features\Sites\Actions\IssueCatalogToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RotateCatalogToken;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;

describe('site catalog tokens', function (): void {
    test('issuing stores encrypted and returns plaintext once', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['domain' => 'token-issue.example']);

        expect($site->hasCatalogToken())->toBeFalse();

        $token = app(IssueCatalogToken::class)->execute($site);
        $fresh = $site->fresh();

        expect($token)->toHaveLength(48)
            ->and($fresh->hasCatalogToken())->toBeTrue()
            ->and($fresh->catalog_token_issued_at)->not->toBeNull()
            ->and(decrypt($fresh->catalog_token_encrypted))->toBe($token);
    });

    test('rotation invalidates the previous token', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['domain' => 'token-rotate.example']);

        $first = app(IssueCatalogToken::class)->execute($site);
        $second = app(RotateCatalogToken::class)->execute($site);

        expect($second)->not->toBe($first)
            ->and(decrypt($site->fresh()->catalog_token_encrypted))->toBe($second);
    });

    test('token matching is constant-time and fails closed', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['domain' => 'token-match.example']);
        $token = app(IssueCatalogToken::class)->execute($site);
        $site = $site->fresh();

        expect($site->matchesCatalogToken($token))->toBeTrue()
            ->and($site->matchesCatalogToken('wrong-token'))->toBeFalse()
            ->and($site->matchesCatalogToken(null))->toBeFalse()
            ->and($site->matchesCatalogToken(''))->toBeFalse();

        $site->forceFill(['catalog_token_encrypted' => 'not-encrypted'])->save();

        expect($site->fresh()->matchesCatalogToken('not-encrypted'))->toBeFalse();
    });
});
