<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Filament\Widgets\CommerceHealthWidget;
use Illuminate\Support\Facades\Gate;
use Spatie\Health\Facades\Health;

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
