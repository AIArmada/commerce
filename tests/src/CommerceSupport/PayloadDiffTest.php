<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\PayloadDiff;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;

enum PayloadDiffProbeStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
}

enum PayloadDiffProbeFlag
{
    case Yes;
    case No;
}

it('returns only keys whose value changed', function (): void {
    $changes = PayloadDiff::changed(
        ['name' => 'New name', 'price' => 100, 'added' => true],
        ['name' => 'Old name', 'price' => 100],
    );

    expect($changes)->toBe(['name' => 'New name']);
});

it('treats equal values across instances and types as unchanged', function (): void {
    $state = [
        'starts_at' => Carbon::parse('2026-01-01 10:00:00'),
        'status' => PayloadDiffProbeStatus::Active,
        'flag' => PayloadDiffProbeFlag::Yes,
        'meta' => ['b' => 2, 'a' => 1],
        'owner_id' => '5',
    ];
    $original = [
        'starts_at' => CarbonImmutable::parse('2026-01-01 10:00:00'),
        'status' => 'active',
        'flag' => PayloadDiffProbeFlag::Yes,
        'meta' => ['a' => 1, 'b' => 2],
        'owner_id' => 5,
    ];

    expect(PayloadDiff::changed($state, $original))->toBe([]);
});

it('compares arrayable values by their array form', function (): void {
    $arrayable = new class implements Arrayable
    {
        public function toArray(): array
        {
            return ['a' => 1];
        }
    };

    expect(PayloadDiff::equal($arrayable, ['a' => 1]))->toBeTrue()
        ->and(PayloadDiff::equal($arrayable, ['a' => 2]))->toBeFalse();
});

it('keeps list order significant but mapping order irrelevant', function (): void {
    expect(PayloadDiff::equal(['a', 'b'], ['b', 'a']))->toBeFalse()
        ->and(PayloadDiff::equal(['x' => 1, 'y' => 2], ['y' => 2, 'x' => 1]))->toBeTrue();
});

it('only coerces clean integer identifier strings', function (): void {
    expect(PayloadDiff::equal('5', 5, 'owner_id'))->toBeTrue()
        ->and(PayloadDiff::equal('05', 5, 'owner_id'))->toBeFalse()
        ->and(PayloadDiff::equal('5', 5, 'name'))->toBeFalse();
});

it('compares objects by string form or json encoding', function (): void {
    $stringable = new class
    {
        public function __toString(): string
        {
            return 'same';
        }
    };

    expect(PayloadDiff::equal($stringable, 'same'))->toBeTrue()
        ->and(PayloadDiff::equal((object) ['a' => 1], (object) ['a' => 1]))->toBeTrue()
        ->and(PayloadDiff::equal((object) ['a' => 1], (object) ['a' => 2]))->toBeFalse();
});
