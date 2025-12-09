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
