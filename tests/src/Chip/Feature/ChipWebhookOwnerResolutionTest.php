<?php

declare(strict_types=1);

use AIArmada\Chip\Http\Controllers\WebhookController;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Testing\WebhookFactory;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Support\Facades\Route;

it('assigns purchase owner from brand_id mapping when owner context is missing', function (): void {
    Route::post('/chip/webhook-test', [WebhookController::class, 'handle'])
        ->withoutMiddleware([VerifyWebhookSignature::class]);

    config()->set('chip.owner.enabled', true);

    $owner = User::query()->create([
        'name' => 'Webhook Owner',
        'email' => 'webhook-owner@example.com',
        'password' => 'secret',
    ]);

    config()->set('chip.owner.webhook_brand_id_map', [
        'brand-1' => [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ],
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $payload = WebhookFactory::purchaseCreated([
        'brand_id' => 'brand-1',
    ]);

    $this->postJson('/chip/webhook-test', $payload)
        ->assertStatus(200);

    /** @var Purchase|null $purchase */
    $purchase = Purchase::query()->withoutOwnerScope()->where('id', $payload['id'])->first();

    expect($purchase)->not->toBeNull();
    expect($purchase?->owner_type)->toBe($owner->getMorphClass());
    expect($purchase?->owner_id)->toBe((string) $owner->getKey());
});

it('fails closed when owner scoping is enabled but brand_id has no owner mapping', function (): void {
    Route::post('/chip/webhook-test', [WebhookController::class, 'handle'])
        ->withoutMiddleware([VerifyWebhookSignature::class]);

    config()->set('chip.owner.enabled', true);
    config()->set('chip.owner.webhook_brand_id_map', []);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $payload = WebhookFactory::purchaseCreated([
        'brand_id' => 'brand-missing',
    ]);

    $this->postJson('/chip/webhook-test', $payload)
        ->assertStatus(500);

    expect(Purchase::query()->withoutOwnerScope()->where('id', $payload['id'])->exists())->toBeFalse();
});

it('fails closed when the brand mapping has an empty owner type', function (): void {
    Route::post('/chip/webhook-test', [WebhookController::class, 'handle'])
        ->withoutMiddleware([VerifyWebhookSignature::class]);

    config()->set('chip.owner.enabled', true);

    config()->set('chip.owner.webhook_brand_id_map', [
        'brand-empty-owner-type' => [
            'owner_type' => '',
            'owner_id' => 'owner-123',
        ],
    ]);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $payload = WebhookFactory::purchaseCreated([
        'brand_id' => 'brand-empty-owner-type',
    ]);

    $this->postJson('/chip/webhook-test', $payload)
        ->assertStatus(500);

    expect(Purchase::query()->withoutOwnerScope()->where('id', $payload['id'])->exists())->toBeFalse();
});

it('stores matching webhook payloads separately for different owners', function (): void {
    Route::post('/chip/webhook-test', [WebhookController::class, 'handle'])
        ->withoutMiddleware([VerifyWebhookSignature::class]);

    config()->set('chip.owner.enabled', true);

    $ownerOne = User::query()->create([
        'name' => 'Webhook Owner One',
        'email' => 'webhook-owner-one@example.com',
        'password' => 'secret',
    ]);

    $ownerTwo = User::query()->create([
        'name' => 'Webhook Owner Two',
        'email' => 'webhook-owner-two@example.com',
        'password' => 'secret',
    ]);

    config()->set('chip.owner.webhook_brand_id_map', [
        'brand-owner-one' => [
            'owner_type' => $ownerOne->getMorphClass(),
            'owner_id' => (string) $ownerOne->getKey(),
        ],
        'brand-owner-two' => [
            'owner_type' => $ownerTwo->getMorphClass(),
            'owner_id' => (string) $ownerTwo->getKey(),
        ],
    ]);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $timestamp = time();

    $payloadOne = WebhookFactory::payoutSuccess([
        'id' => 'payout-shared-id',
        'brand_id' => 'brand-owner-one',
        'created_on' => $timestamp,
        'updated_on' => $timestamp,
    ]);

    $payloadTwo = WebhookFactory::payoutSuccess([
        'id' => 'payout-shared-id',
        'brand_id' => 'brand-owner-two',
        'created_on' => $timestamp,
        'updated_on' => $timestamp,
    ]);

    $this->postJson('/chip/webhook-test', $payloadOne)
        ->assertStatus(200);

    $this->postJson('/chip/webhook-test', $payloadTwo)
        ->assertStatus(200);

    $webhooks = Webhook::query()
        ->withoutOwnerScope()
        ->where('event_type', 'payout.success')
        ->get();

    expect($webhooks)->toHaveCount(2)
        ->and($webhooks->pluck('owner_id')->sort()->values()->all())
        ->toBe(collect([(string) $ownerOne->getKey(), (string) $ownerTwo->getKey()])->sort()->values()->all());
});
