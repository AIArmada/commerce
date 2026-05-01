<?php

declare(strict_types=1);

use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;

test('affiliate links are signed and verified', function (): void {
    $generator = new AffiliateLinkGenerator;

    $url = $generator->generate(
        affiliateCode: 'LINK123',
        url: 'https://shop.test/landing',
        params: ['utm_source' => 'newsletter'],
        ttlSeconds: 120
    );

    expect($url)->toContain('aff=LINK123')
        ->and($url)->toContain('aff_sig=');

    expect($generator->verify($url))->toBeTrue();

    $tampered = str_replace('LINK123', 'BADCODE', $url);

    expect($generator->verify($tampered))->toBeFalse();
});

test('affiliate links reject non-http schemes', function (): void {
    $generator = new AffiliateLinkGenerator;

    expect(fn () => $generator->generate(
        affiliateCode: 'LINK123',
        url: 'javascript:alert(1)',
    ))->toThrow(InvalidArgumentException::class, 'Link URL scheme must be http or https.');
});
