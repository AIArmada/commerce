<?php

declare(strict_types=1);

use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Widgets\AccountBalanceWidget;
use AIArmada\FilamentChip\Widgets\AccountTurnoverWidget;
use Illuminate\Database\Eloquent\Model;

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
        ->and($data['revenue'])->toBe(array_fill(0, 30, 0.0))
        ->and($data['fees'])->toBe(array_fill(0, 30, 0.0));
});

it('keeps successful day buckets when a later day fails', function (): void {
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
        ->and($data['revenue'])->toBe([150.0, 150.0, ...array_fill(0, 28, 0.0)])
        ->and($data['fees'])->toBe([4.5, 4.5, ...array_fill(0, 28, 0.0)]);
});

it('serves a warm render from cached day buckets without remote calls', function (): void {
    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getAccountTurnover')
        ->times(30)
        ->andReturn(['incoming' => ['turnover' => 15000, 'fee_sell' => 450, 'count' => 3]]);
    app()->instance(ChipCollectService::class, $service);

    $method = new ReflectionMethod(AccountTurnoverWidget::class, 'getTurnoverData');
    $widget = app(AccountTurnoverWidget::class);

    $cold = $method->invoke($widget);
    $warm = $method->invoke($widget);

    expect($cold['revenue'])->toBe($warm['revenue'])
        ->and($cold['fees'])->toBe($warm['fees']);
});

it('does not expose company turnover without explicit global context in owner mode', function (): void {
    config()->set('chip.owner.enabled', true);

    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldNotReceive('getAccountTurnover');
    app()->instance(ChipCollectService::class, $service);

    $owner = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'team';
        }

        public function getKey(): string
        {
            return 'team-turnover';
        }
    };

    OwnerContext::withOwner($owner, function (): void {
        $method = new ReflectionMethod(AccountTurnoverWidget::class, 'getTurnoverData');
        $data = $method->invoke(app(AccountTurnoverWidget::class));

        expect($data['revenue'])->toBe(array_fill(0, 30, 0.0))
            ->and(AccountTurnoverWidget::canView())->toBeFalse();
    });

    OwnerContext::withOwner(null, function (): void {
        expect(AccountTurnoverWidget::canView())->toBeTrue();
    });
});

it('caches company balance and hides it from tenant owner context', function (): void {
    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getAccountBalance')
        ->once()
        ->andReturn(['available_balance' => 1000, 'pending_balance' => 200, 'reserved_balance' => 50]);
    app()->instance(ChipCollectService::class, $service);

    $method = new ReflectionMethod(AccountBalanceWidget::class, 'getAccountBalance');
    $widget = app(AccountBalanceWidget::class);

    expect($method->invoke($widget))->toBe(['available' => 1000, 'pending' => 200, 'reserved' => 50])
        ->and($method->invoke($widget))->toBe(['available' => 1000, 'pending' => 200, 'reserved' => 50]);

    config()->set('chip.owner.enabled', true);

    $owner = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'team';
        }

        public function getKey(): string
        {
            return 'team-balance';
        }
    };

    OwnerContext::withOwner($owner, function (): void {
        expect(AccountBalanceWidget::canView())->toBeFalse();
    });

    OwnerContext::withOwner(null, function (): void {
        expect(AccountBalanceWidget::canView())->toBeTrue();
    });
});
