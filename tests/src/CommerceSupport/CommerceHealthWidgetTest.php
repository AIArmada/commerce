<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Filament\Widgets\CommerceHealthWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Spatie\Health\Facades\Health;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;

it('requires explicit ability to view commerce health widget', function (): void {
    if (! class_exists(Health::class)) {
        $this->markTestSkipped('Spatie Health is not installed.');
    }

    app()->instance('health', new stdClass);

    $user = User::query()->create([
        'name' => 'Health Viewer',
        'email' => 'health-viewer@example.com',
        'password' => 'secret',
    ]);

    $other = User::query()->create([
        'name' => 'No Access',
        'email' => 'health-no-access@example.com',
        'password' => 'secret',
    ]);

    config()->set('commerce-support.health.view_ability', 'viewCommerceHealth');

    Gate::define('viewCommerceHealth', function (User $authUser) use ($user): bool {
        return (string) $authUser->getKey() === (string) $user->getKey();
    });

    auth()->login($user);
    expect(CommerceHealthWidget::canView())->toBeTrue();

    auth()->login($other);
    expect(CommerceHealthWidget::canView())->toBeFalse();

    auth()->logout();
    expect(CommerceHealthWidget::canView())->toBeFalse();
});

it('reads stored health results once per widget instance', function (): void {
    if (! class_exists(Health::class)) {
        $this->markTestSkipped('Spatie Health is not installed.');
    }

    app()->instance('health', new stdClass);

    $user = User::query()->create([
        'name' => 'Health Reader',
        'email' => 'health-reader@example.com',
        'password' => 'secret',
    ]);

    config()->set('commerce-support.health.view_ability', 'viewCommerceHealth');

    Gate::define('viewCommerceHealth', fn (): bool => true);

    auth()->login($user);

    $calls = 0;

    $store = new class($calls) implements ResultStore
    {
        public function __construct(private int &$calls) {}

        public function save(Collection $checkResults): void {}

        public function latestResults(): ?StoredCheckResults
        {
            $this->calls++;

            return new StoredCheckResults(checkResults: collect([
                StoredCheckResult::make('DatabaseCheck', 'Database', null, 'all good', 'ok', []),
                StoredCheckResult::make('CacheCheck', '', null, 'slow', 'warning', ['latency_ms' => 900]),
            ]));
        }
    };

    app()->instance(ResultStore::class, $store);

    $widget = new CommerceHealthWidget;

    $results = $widget->getHealthResults();
    $widget->getOverallStatus();
    $widget->getStatusCounts();

    expect($calls)->toBe(1)
        ->and($results)->toHaveCount(2)
        ->and($results[0]['label'])->toBe('Database')
        ->and($results[0]['status'])->toBe('ok')
        ->and($results[1]['label'])->toBe('Cache')
        ->and($results[1]['status'])->toBe('warning')
        ->and($results[1]['meta'])->toBe(['latency_ms' => 900]);
});
