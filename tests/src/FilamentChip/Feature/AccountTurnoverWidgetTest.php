<?php

declare(strict_types=1);

use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\FilamentChip\Widgets\AccountTurnoverWidget;

it('maps incoming turnover pairs to a non-zero 30-day series', function (): void {
    $seen = [];

    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getAccountTurnover')
        ->times(30)
        ->withArgs(function (array $filters) use (&$seen): bool {
            $seen[] = $filters;

            return is_int($filters['from'] ?? null)
                && is_int($filters['to'] ?? null)
                && ($filters['currency'] ?? null) === 'MYR';
        })
        ->andReturn(['incoming' => ['turnover' => 15000, 'fee_sell' => 450, 'count' => 3]]);
    app()->instance(ChipCollectService::class, $service);

    $method = new ReflectionMethod(AccountTurnoverWidget::class, 'getTurnoverData');
    $data = $method->invoke(app(AccountTurnoverWidget::class));

    expect($data['labels'])->toHaveCount(30)
        ->and($data['revenue'])->toHaveCount(30)
        ->and($data['fees'])->toHaveCount(30)
        ->and($data['revenue'][0])->toBe(150.0)
        ->and($data['fees'][0])->toBe(4.5)
        ->and($seen)->toHaveCount(30);
});

it('zero-fills the series when turnover fails', function (): void {
    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getAccountTurnover')->andThrow(new RuntimeException('api down'));
    app()->instance(ChipCollectService::class, $service);

    $method = new ReflectionMethod(AccountTurnoverWidget::class, 'getTurnoverData');
    $data = $method->invoke(app(AccountTurnoverWidget::class));

    expect($data['labels'])->toHaveCount(30)
        ->and($data['revenue'])->toBe(array_fill(0, 30, 0))
        ->and($data['fees'])->toBe(array_fill(0, 30, 0));
});

it('resets partial days when turnover fails mid-loop', function (): void {
    $calls = 0;
    $pair = ['incoming' => ['turnover' => 15000, 'fee_sell' => 450, 'count' => 3]];

    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getAccountTurnover')
        ->andReturnUsing(function () use (&$calls, $pair): array {
            $calls++;

            if ($calls <= 2) {
                return $pair;
            }

            throw new RuntimeException('api down');
        });
    app()->instance(ChipCollectService::class, $service);

    $method = new ReflectionMethod(AccountTurnoverWidget::class, 'getTurnoverData');
    $data = $method->invoke(app(AccountTurnoverWidget::class));

    expect($data['labels'])->toHaveCount(30)
        ->and($data['revenue'])->toBe(array_fill(0, 30, 0))
        ->and($data['fees'])->toBe(array_fill(0, 30, 0));
});
