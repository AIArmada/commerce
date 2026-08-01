<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\StringSimilarity;

it('normalizes text consistently for similarity comparisons', function (): void {
    expect(StringSimilarity::normalize('  Ál-MuTtaqīn  '))->toBe('al muttaqin');
});

it('returns the stronger of edit distance and similar text scores', function (): void {
    expect(StringSimilarity::score('imam', 'imam'))->toBe(1.0)
        ->and(StringSimilarity::score('', 'imam'))->toBe(0.0)
        ->and(StringSimilarity::score('imam', 'ustaz'))->toBeLessThan(1.0);
});
