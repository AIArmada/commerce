<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\StableModelOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $make = static function (int $id, ?string $createdAt): Model {
        $model = new class extends Model
        {
            protected $guarded = [];
        };

        $model->forceFill([
            'id' => $id,
            'created_at' => $createdAt === null ? null : Carbon::parse($createdAt),
        ]);

        return $model;
    };

    $this->records = new Collection([
        $make(3, '2026-03-01 10:00:00'),
        $make(1, '2026-01-01 10:00:00'),
        $make(2, '2026-01-01 10:00:00'),
        $make(4, null),
    ]);
});

it('sorts by created at with the key as tiebreak and missing dates first', function (): void {
    $keys = StableModelOrder::sort($this->records)->map(static fn (Model $record) => $record->getKey())->all();

    expect($keys)->toBe([4, 1, 2, 3]);
});

it('runs callbacks in stable order and reports whether anything changed', function (): void {
    $visited = [];

    $didChange = StableModelOrder::sync($this->records, function (Model $record) use (&$visited): bool {
        $visited[] = $record->getKey();

        return $record->getKey() === 2;
    });

    expect($visited)->toBe([4, 1, 2, 3])
        ->and($didChange)->toBeTrue();
});

it('reports unchanged when no callback run changes anything', function (): void {
    expect(StableModelOrder::sync($this->records, static fn (): bool => false))->toBeFalse();
});

it('finds the one-based sequence of a record', function (): void {
    expect(StableModelOrder::sequence($this->records, 2))->toBe(3)
        ->and(StableModelOrder::sequence($this->records, '4'))->toBe(1)
        ->and(StableModelOrder::sequence($this->records, 99))->toBeNull();
});
