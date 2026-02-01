<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\BuildCheckoutSessionViewData;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Database\Eloquent\Model;

it('builds checkout session view data', function (): void {
    $session = new CheckoutSession;
    $session->id = 'session-1';
    $session->currency = 'MYR';
    $session->grand_total = 1234;
    $session->shipping_data = ['name' => 'Test User'];
    $session->payment_data = ['reference' => 'ref-123'];

    $order = new class extends Model
    {
        protected $guarded = [];
    };
    $order->id = 'order-1';
    $order->order_number = 'ORD-1';

    $session->setRelation('order', $order);

    $data = BuildCheckoutSessionViewData::run($session);

    expect($data)->toHaveKeys(['session', 'order', 'reference', 'formattedTotal', 'shippingData'])
        ->and($data['session'])->toBe($session)
        ->and($data['order'])->toBe($order)
        ->and($data['reference'])->toBe('ref-123')
        ->and($data['formattedTotal'])->toBeString()
        ->and($data['shippingData'])->toBe(['name' => 'Test User']);
});
