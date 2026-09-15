<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\FixedValueRedactor;

it('redacts any value to a fixed marker', function (): void {
    expect(FixedValueRedactor::redact('s3cret'))->toBe('[REDACTED]')
        ->and(FixedValueRedactor::redact(null))->toBe('[REDACTED]');
});
