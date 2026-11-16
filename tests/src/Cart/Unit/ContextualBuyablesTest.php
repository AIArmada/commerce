<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartValidationResult;
use AIArmada\Cart\Contracts\ContextualBuyableValidatorInterface;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\CommerceSupport\Support\PurchaseContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Cart\InMemoryStorage;

final class ContextTestBuyable extends Model implements Buyable
{
    public $timestamps = false;

    protected $guarded = [];

    public function __construct()
    {
        parent::__construct(['id' => 'context-item']);
    }

    public function getBuyableIdentifier(): string
    {
        return 'context-item';
    }

    public function getBuyableCatalogIdentifier(): ?string
    {
        return 'context-item';
    }

    public function getBuyableName(): string
    {
        return 'Context Item';
    }

    public function getBuyablePrice(): int
    {
        return 1000;
    }

    public function getBuyableCurrency(): string
    {
        return 'USD';
    }

    public function getBuyableAttributes(): array
    {
        return ['sku' => 'CONTEXT-ITEM', 'requires_shipping' => false];
    }

    public function canBePurchased(?int $quantity = null): bool
    {
        return $quantity === null || $quantity <= 10;
    }

    public function getBuyableStock(): ?int
    {
        return 10;
    }

    public function getBuyableMinimumQuantity(): int
    {
        return 1;
    }

    public function getBuyableMaximumQuantity(): ?int
    {
        return null;
    }

    public function getBuyableQuantityIncrement(): int
    {
        return 1;
    }
}

final class ContextTestValidator implements ContextualBuyableValidatorInterface
{
    /** @var array<string, int> */
    public array $capacity = ['am' => 4, 'pm' => 6];

    public function validate(Buyable $buyable, int $quantity, PurchaseContext $context): CartValidationResult
    {
        $window = (string) ($context->values['window'] ?? '');
        $available = $this->capacity[$window] ?? 0;

        return $quantity <= $available
            ? CartValidationResult::valid()
            : CartValidationResult::invalid('The selected window has insufficient capacity.');
    }
}

it('canonicalizes purchase selections without losing list ordering', function (): void {
    $one = new PurchaseContext(['window' => 'am', 'options' => ['b' => 2, 'a' => [3, 1]]]);
    $two = new PurchaseContext(['options' => ['a' => [3, 1], 'b' => 2], 'window' => 'am']);
    $different = new PurchaseContext(['window' => 'am', 'options' => ['a' => [1, 3], 'b' => 2]]);

    expect($one->fingerprint)->toBe($two->fingerprint)
        ->not->toBe($different->fingerprint)
        ->and(PurchaseContext::fromAttributes([
            'purchase_context' => $two->values,
            'purchase_context_fingerprint' => $two->fingerprint,
        ])?->fingerprint)->toBe($one->fingerprint);
});

it('rejects unsupported or modified purchase context values', function (): void {
    $selection = new PurchaseContext(['window' => 'am']);

    expect(fn () => new PurchaseContext(['ratio' => 1.3]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => PurchaseContext::fromAttributes([
            'purchase_context' => ['window' => 'pm'],
            'purchase_context_fingerprint' => $selection->fingerprint,
        ]))->toThrow(InvalidArgumentException::class);
});

it('keeps contextual cart lines separate and aggregates identical selections', function (): void {
    $cart = new Cart(new InMemoryStorage, 'contextual-cart');
    $buyable = new ContextTestBuyable;
    $am = new PurchaseContext(['window' => 'am']);
    $sameAm = new PurchaseContext(['window' => 'am']);
    $pm = new PurchaseContext(['window' => 'pm']);

    $morning = $cart->addBuyable($buyable, 2, [], null, $am);
    $cart->addBuyable($buyable, 1, [], null, $sameAm);
    $evening = $cart->addBuyable($buyable, 3, [], null, $pm);

    expect($morning->id)->not->toBe($evening->id)
        ->and($cart->getBuyable($buyable, null, $am)?->quantity)->toBe(3)
        ->and($cart->getBuyable($buyable, null, $pm)?->quantity)->toBe(3)
        ->and($cart->hasBuyable($buyable, null, $am))->toBeTrue()
        ->and($cart->getBuyable($buyable, null, $am)?->getAttribute('purchase_context_fingerprint'))->toBe($am->fingerprint);

    $cart->updateBuyable($buyable, 4, null, [], $am);
    expect($cart->getBuyable($buyable, null, $am)?->quantity)->toBe(4);

    expect(fn () => $cart->addBuyable($buyable, 4, [], null, $pm))
        ->toThrow(\AIArmada\Cart\Exceptions\ProductNotPurchasableException::class);
});

it('rejects changing selection of an existing contextual line', function (): void {
    $cart = new Cart(new InMemoryStorage, 'contextual-guard');
    $buyable = new ContextTestBuyable;
    $am = new PurchaseContext(['window' => 'am']);
    $pm = new PurchaseContext(['window' => 'pm']);
    $item = $cart->addBuyable($buyable, 1, [], 'selected-service', $am);

    expect(fn () => $cart->addBuyable($buyable, 1, [], 'selected-service', $pm))
        ->toThrow(InvalidCartItemException::class)
        ->and(fn () => $cart->update('selected-service', ['quantity' => 1]))
        ->toThrow(InvalidCartItemException::class)
        ->and(fn () => $cart->addBuyable($buyable, 1, ['purchase_context' => ['window' => 'fake']]))
        ->not->toThrow(Throwable::class)
        ->and($item->getAttribute('purchase_context'))->toBe(['window' => 'am']);
});

it('validates context capacity on cart add and revalidation', function (): void {
    $validator = new ContextTestValidator;
    app()->instance(ContextualBuyableValidatorInterface::class, $validator);

    $cart = new Cart(new InMemoryStorage, 'context-policy-cart');
    $buyable = Product::query()->create([
        'name' => 'Persisted Context Service',
        'slug' => 'persisted-context-service-' . Str::random(8),
        'sku' => 'CTX-' . mb_strtoupper(Str::random(8)),
        'type' => ProductType::Service,
        'status' => ProductStatus::Active,
        'price' => 1000,
        'currency' => 'USD',
    ]);
    $am = new PurchaseContext(['window' => 'am']);
    $pm = new PurchaseContext(['window' => 'pm']);

    $cart->addBuyable($buyable, 3, [], null, $am);

    expect(fn () => $cart->addBuyable($buyable, 2, [], null, $am))
        ->toThrow(InvalidCartItemException::class, 'insufficient capacity');

    $cart->addBuyable($buyable, 5, [], null, $pm);
    expect($cart->validateContextualBuyables())->toBe([]);

    $validator->capacity['pm'] = 3;
    $errors = $cart->validateContextualBuyables();

    expect($errors)->toHaveCount(1)
        ->and(implode(' ', $errors))->toContain('insufficient capacity');
});
