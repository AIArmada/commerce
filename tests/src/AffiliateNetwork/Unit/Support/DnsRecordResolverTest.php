<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Drivers\NativeDnsDriver;

it('handles dns warnings and returns an empty record list', function (): void {
    $warnings = [];

    set_error_handler(function (int $severity, string $message) use (&$warnings): bool {
        $warnings[] = [$severity, $message];

        return true;
    });

    try {
        $records = (new NativeDnsDriver)->getTxtRecords('invalid host name');
    } finally {
        restore_error_handler();
    }

    expect($records)->toBe([])
        ->and($warnings)->toBe([]);
});
