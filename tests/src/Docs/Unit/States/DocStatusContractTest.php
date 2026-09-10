<?php

declare(strict_types=1);

use AIArmada\Docs\States\DocStatus;

test('the canonical status states preserve persisted status values', function (): void {
    $persistedValues = [
        'draft',
        'pending',
        'sent',
        'paid',
        'partially_paid',
        'overdue',
        'cancelled',
        'refunded',
    ];

    $canonicalValues = array_values(array_map(
        static fn (string $stateClass): string => $stateClass::value(),
        DocStatus::all()->all(),
    ));

    expect($canonicalValues)->toEqualCanonicalizing($persistedValues);

    foreach ($persistedValues as $value) {
        expect(DocStatus::resolveStateClassFor($value)::value())->toBe($value);
    }
});
