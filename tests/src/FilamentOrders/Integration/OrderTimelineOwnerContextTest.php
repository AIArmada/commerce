<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrders\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentOrders\Widgets\OrderTimelineWidget;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

function timelineOwner(string $name): TestOwner
{
    return TestOwner::query()->create(['name' => $name]);
}

function timelineOrder(TestOwner $owner): Order
{
    return OwnerContext::withOwner($owner, function () use ($owner): Order {
        $order = Order::query()->make([
            'status' => Created::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);
        $order->assignOwner($owner);
        $order->save();

        return $order;
    });
}

it('stamps a visible order at mount and renders its timeline', function (): void {
    $owner = timelineOwner('Timeline Owner A');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $widget = app(OrderTimelineWidget::class);
    $widget->mount(timelineOrder($owner));
    $widget->mountVerifiesRecordOwnerContext();

    expect($widget->record)->not->toBeNull()
        ->and($widget->recordOwnerStamp)->toBeString()
        ->and($widget->getTimelineEvents()->pluck('type')->all())->toContain('created');
});

it('fails closed at mount for a cross-owner order', function (): void {
    $ownerA = timelineOwner('Timeline Mount A');
    $ownerB = timelineOwner('Timeline Mount B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $widget = app(OrderTimelineWidget::class);
    $widget->mount(timelineOrder($ownerA));
    $widget->mountVerifiesRecordOwnerContext();

    expect($widget->record)->toBeNull()
        ->and($widget->recordOwnerStamp)->toBeNull()
        ->and($widget->getTimelineEvents())->toBeEmpty();
});

it('keeps rendering when the owner context is unchanged', function (): void {
    $owner = timelineOwner('Timeline Stable Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $widget = app(OrderTimelineWidget::class);
    $widget->mount(timelineOrder($owner));
    $widget->mountVerifiesRecordOwnerContext();
    $widget->hydrateVerifiesRecordOwnerContext();

    expect($widget->record)->not->toBeNull()
        ->and($widget->getTimelineEvents()->pluck('type')->all())->toContain('created');
});

it('clears the record and timeline after an owner switch', function (): void {
    $ownerA = timelineOwner('Timeline Switch A');
    $ownerB = timelineOwner('Timeline Switch B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $widget = app(OrderTimelineWidget::class);
    $widget->mount(timelineOrder($ownerA));
    $widget->mountVerifiesRecordOwnerContext();

    expect($widget->getTimelineEvents())->not->toBeEmpty();

    OwnerContext::withOwner($ownerB, function () use ($widget): void {
        $widget->hydrateVerifiesRecordOwnerContext();
    });

    expect($widget->record)->toBeNull()
        ->and($widget->recordOwnerStamp)->toBeNull()
        ->and($widget->getTimelineEvents())->toBeEmpty();
});

it('exposes the owner guard hooks and locked record props', function (): void {
    $widget = app(OrderTimelineWidget::class);

    expect(method_exists($widget, 'mountVerifiesRecordOwnerContext'))->toBeTrue()
        ->and(method_exists($widget, 'hydrateVerifiesRecordOwnerContext'))->toBeTrue()
        ->and((new ReflectionProperty($widget, 'record'))->getAttributes(Locked::class))->not->toBeEmpty()
        ->and((new ReflectionProperty($widget, 'recordOwnerStamp'))->getAttributes(Locked::class))->not->toBeEmpty();
});
