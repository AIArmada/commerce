<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use App\Models\User;
use Database\Seeders\AnalyticsShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('checkout renders growth experiment messaging without a tracker cookie', function (): void {
    User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    $owner = User::query()->where('email', 'admin@commerce.demo')->firstOrFail();

    OwnerContext::withOwner($owner, function (): void {
        $this->seed(AnalyticsShowcaseSeeder::class);

        Product::create([
            'name' => 'Growth Demo Product',
            'sku' => 'GROWTH-DEMO-001',
            'price' => 12_500,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    $product = OwnerContext::withOwner($owner, fn (): Product => Product::query()->firstOrFail());
    $session = ['demo_owner_id' => $owner->id];

    $this->withSession($session)->post(route('shop.cart.add'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertRedirect();

    $this->withSession($session)
        ->withCookie('mi_signals_anonymous_id', 'tracker-anonymous-demo')
        ->get(route('shop.checkout'))
        ->assertOk()
        ->assertSee('Growth experiment live')
        ->assertSee('data-checkout-experiment="storefront-checkout-layout-test"', false);
});

test('checkout stores growth visitor and experiment context in the checkout session payload', function (): void {
    config()->set('chip.collect.api_key', null);
    config()->set('chip.collect.brand_id', null);

    User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    $owner = User::query()->where('email', 'admin@commerce.demo')->firstOrFail();

    OwnerContext::withOwner($owner, function (): void {
        $this->seed(AnalyticsShowcaseSeeder::class);

        Product::create([
            'name' => 'Growth Checkout Product',
            'sku' => 'GROWTH-CHECKOUT-001',
            'price' => 18_900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    $product = OwnerContext::withOwner($owner, fn (): Product => Product::query()->firstOrFail());
    $anonymousId = 'mi-signals-demo-checkout-visitor';
    $session = ['demo_owner_id' => $owner->id];

    $assignment = OwnerContext::withOwner($owner, function () use ($anonymousId): mixed {
        $experiment = Experiment::query()
            ->where('slug', 'storefront-checkout-layout-test')
            ->firstOrFail();

        return app(ResolveExperimentAssignment::class)->handle($experiment, anonymousId: $anonymousId);
    });

    $this->withSession($session)->post(route('shop.cart.add'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertRedirect();

    $response = $this->withSession($session)
        ->withCookie('mi_signals_anonymous_id', $anonymousId)
        ->post(route('shop.checkout.process'), [
            'email' => 'growth-checkout@example.com',
            'phone' => '+60123456789',
            'first_name' => 'Growth',
            'last_name' => 'Buyer',
            'line1' => '1 Jalan Demo',
            'line2' => null,
            'city' => 'Kuala Lumpur',
            'state' => 'Selangor',
            'postcode' => '50000',
            'shipping_method' => 'jnt_standard',
            'payment_method' => 'fpx',
        ]);

    $checkoutSession = OwnerContext::withOwner($owner, fn (): ?CheckoutSession => CheckoutSession::query()->latest('created_at')->first());

    expect($checkoutSession)->not()->toBeNull();

    if (! $checkoutSession instanceof CheckoutSession) {
        return;
    }

    $response->assertRedirect(route('demo.payment.show', ['checkoutSession' => $checkoutSession]));

    expect(data_get($checkoutSession->payment_data, 'growth_visitor_id'))->toBe($anonymousId)
        ->and(data_get($checkoutSession->payment_data, 'experiment_contexts.0.experiment_slug'))->toBe('storefront-checkout-layout-test')
        ->and(data_get($checkoutSession->payment_data, 'experiment_contexts.0.variant_code'))->toBe($assignment->variant->code)
        ->and(data_get($checkoutSession->payment_data, 'experiment_contexts.0.assignment_id'))->toBe((string) $assignment->getKey())
        ->and(data_get($checkoutSession->billing_data, 'metadata.growth_visitor_id'))->toBe($anonymousId)
        ->and(data_get($checkoutSession->billing_data, 'metadata.experiment_contexts.0.experiment_slug'))->toBe('storefront-checkout-layout-test')
        ->and(data_get($checkoutSession->billing_data, 'metadata.experiment_contexts.0.variant_code'))->toBe($assignment->variant->code);
});
