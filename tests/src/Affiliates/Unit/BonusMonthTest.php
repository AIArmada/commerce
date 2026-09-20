<?php

declare(strict_types=1);

use AIArmada\Affiliates\Support\BonusMonth;
use Carbon\CarbonImmutable;

describe('BonusMonth', function (): void {
    it('parses a valid month', function (): void {
        $range = BonusMonth::parse('2026-02');

        expect($range)->not->toBeNull()
            ->and($range[0]->format('Y-m-d'))->toBe('2026-02-01')
            ->and($range[1]->format('Y-m-d'))->toBe('2026-02-28');
    });

    it('defaults blank input to the current month', function (): void {
        $now = CarbonImmutable::now();

        expect(BonusMonth::parse(null)[0]->format('Y-m'))->toBe($now->format('Y-m'))
            ->and(BonusMonth::parse('')[1]->format('Y-m'))->toBe($now->format('Y-m'));
    });

    it('rejects invalid months', function (): void {
        expect(BonusMonth::parse('2026-13'))->toBeNull()
            ->and(BonusMonth::parse('2026-00'))->toBeNull()
            ->and(BonusMonth::parse('2026-02-15'))->toBeNull()
            ->and(BonusMonth::parse('not-a-month'))->toBeNull()
            ->and(BonusMonth::parse(202602))->toBeNull();
    });
});
