<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\CartDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

it('streams a cart export with a sanitized unique filename and json payload', function (): void {
    $cart = Cart::query()->create([
        'identifier' => '../guest session/123',
        'instance' => 'wish list',
        'items' => [['id' => 'product-1', 'quantity' => 2]],
        'conditions' => [['name' => 'flash-discount']],
        'metadata' => ['email' => 'customer@example.com'],
        'currency' => 'USD',
    ]);

    $response = app(CartDownloadService::class)->download($cart);

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    $disposition = (string) $response->headers->get('content-disposition');

    expect((string) $response->headers->get('content-type'))->toContain('application/json');
    expect($disposition)->toContain('cart-wish-list-guest-session-123-' . $cart->id . '.json');
    expect($disposition)->not->toContain('../');

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['identifier'])->toBe('../guest session/123');
    expect($payload['instance'])->toBe('wish list');
    expect($payload)->not->toHaveKey('owner_scope');
    expect($payload['items'][0]['id'])->toBe('product-1');
});
