<?php

declare(strict_types=1);

use AIArmada\Affiliates\Exceptions\AffiliateNotFoundException;

test('AffiliateNotFoundException creates correct message', function (): void {
    $exception = AffiliateNotFoundException::withCode('TEST123');

    expect($exception)->toBeInstanceOf(AffiliateNotFoundException::class);
    expect($exception->getMessage())->toBe("Affiliate 'TEST123' was not found or is inactive.");
});