<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentJnt\Actions\SyncTrackingAction;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntTrackingService;

uses(FilamentJntTestCase::class);

it('is only visible when the order has a tracking number', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'User',
        'email' => 'visibility@example.test',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($user);

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-10',
        'customer_code' => 'CUST',
        'tracking_number' => null,
    ]);

    expect(SyncTrackingAction::make()->record($order)->isVisible())->toBeFalse();

    $order->update(['tracking_number' => 'TRK-10']);
    $order->refresh();

    expect(SyncTrackingAction::make()->record($order)->isVisible())->toBeTrue();
});

it('shows an authentication required notification when unauthenticated', function (): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-11',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-11',
    ]);

    $action = SyncTrackingAction::make()->record($order);
    $handler = $action->getActionFunction();

    $handler($order);

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toBe('Authentication Required');
});

it('syncs tracking and shows a success notification when authenticated', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'User',
        'email' => 'user3@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $called = false;

    app()->bind(JntTrackingService::class, function () use (&$called) {
        return new class($called) {
            public function __construct(private bool &$called) {}

            public function syncOrderTracking(JntOrder $order): void
            {
                $this->called = true;
            }
        };
    });

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-12',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-12',
    ]);

    $action = SyncTrackingAction::make()->record($order);
    $handler = $action->getActionFunction();

    $handler($order);

    expect($called)->toBeTrue();

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toBe('Tracking Synced');
});

it('handles sync failures and shows a failure notification', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'User',
        'email' => 'user4@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    app()->bind(JntTrackingService::class, fn () => new class {
        public function syncOrderTracking(JntOrder $order): void
        {
            throw new RuntimeException('fail');
        }
    });

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-13',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-13',
    ]);

    $action = SyncTrackingAction::make()->record($order);
    $handler = $action->getActionFunction();

    $handler($order);

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toBe('Sync Failed');
});
