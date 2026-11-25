<?php

declare(strict_types=1);

use AIArmada\Authz\Support\BackToUrlSanitizer;

describe('Impersonation return URL sanitizer', function (): void {
    it('falls back to root path for external back-to urls', function (): void {
        expect(BackToUrlSanitizer::sanitize('https://evil.example/owned'))->toBe('/')
            ->and(BackToUrlSanitizer::sanitize('//evil.example/owned'))->toBe('/');
    });

    it('keeps safe relative back-to urls', function (): void {
        expect(BackToUrlSanitizer::sanitize('/admin'))->toBe('/admin')
            ->and(BackToUrlSanitizer::sanitize('/'))->toBe('/');
    });
});
