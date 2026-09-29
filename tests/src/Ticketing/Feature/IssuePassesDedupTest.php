<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Processing;
use AIArmada\Ticketing\Events\PassIssued;
use AIArmada\Ticketing\Listeners\IssuePassesOnFulfillment;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Services\DefaultPassIssuer;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Support\Facades\Event;

function createDedupTicketType(): TicketType
{
    return TicketType::factory()->create([
        'ticketable_type' => TicketType::class,
        'ticketable_id' => TicketType::factory()->create()->getKey(),
    ]);
}

function createDedupOrderWithTicketItem(string $number, TicketType $ticketType, int $quantity): Order
{
    $order = Order::create([
        'order_number' => $number,
        'status' => Processing::class,
        'currency' => 'USD',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    OrderItem::create([
        'order_id' => $order->getKey(),
        'purchasable_type' => TicketType::class,
        'purchasable_id' => $ticketType->getKey(),
        'name' => 'Test Ticket',
        'quantity' => $quantity,
        'unit_price' => 5000,
        'total' => 5000 * $quantity,
    ]);

    return $order->fresh();
}

it('does not issue duplicate passes on redelivered fulfillment', function (): void {
    Event::fake([PassIssued::class]);

    $ticketType = createDedupTicketType();
    $order = createDedupOrderWithTicketItem('ORD-DEDUP-1', $ticketType, 2);

    $listener = new IssuePassesOnFulfillment;
    $event = new OrderFulfillmentRequired($order, 'txn-dedup-1', 'stripe');

    $listener->handle($event);

    expect(Pass::count())->toBe(2);

    $listener->handle($event);

    expect(Pass::count())->toBe(2);
});

it('issues only the remainder when passes already exist for the item', function (): void {
    Event::fake([PassIssued::class]);

    $ticketType = createDedupTicketType();
    $order = createDedupOrderWithTicketItem('ORD-DEDUP-2', $ticketType, 3);
    $item = $order->items->first();

    app(DefaultPassIssuer::class)->issuePassesFor(new PassIssuanceContext(
        ticketType: $ticketType,
        quantity: 1,
        metadata: ['order_id' => $order->getKey(), 'order_item_id' => $item->getKey()],
    ));

    expect(Pass::count())->toBe(1);

    (new IssuePassesOnFulfillment)->handle(
        new OrderFulfillmentRequired($order->fresh(), 'txn-dedup-2', 'stripe')
    );

    expect(Pass::count())->toBe(3);
});

it('restores the order owner context when ambient context is missing', function (): void {
    config()->set('ticketing.owner.enabled', true);

    $owner = User::factory()->create();

    [$ticketType, $order] = OwnerContext::withOwner($owner, function (): array {
        $ticketType = createDedupTicketType();

        return [$ticketType, createDedupOrderWithTicketItem('ORD-DEDUP-CTX', $ticketType, 2)];
    });

    // Queue worker with no ambient owner: the listener must restore the
    // order's owner so issuance and the dedup count resolve scoped.
    OwnerContext::flushState();
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    (new IssuePassesOnFulfillment)->handle(
        new OrderFulfillmentRequired($order, 'txn-dedup-ctx', 'stripe')
    );

    expect(Pass::query()->withoutOwnerScope()->count())->toBe(2);
});
