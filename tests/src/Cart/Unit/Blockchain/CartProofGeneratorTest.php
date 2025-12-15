<?php

declare(strict_types=1);

use AIArmada\Cart\Blockchain\CartProofGenerator;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Support\Collection;

describe('CartProofGenerator', function (): void {
    beforeEach(function (): void {
        $this->generator = new CartProofGenerator;
        $this->storage = new InMemoryStorage;
        // Set a default signing key for HMAC signing in tests
        config(['cart.blockchain.signing_key' => 'test-key-for-blockchain-signing']);
    });

    it('can be instantiated', function (): void {
        expect($this->generator)->toBeInstanceOf(CartProofGenerator::class);
    });

    it('generates proof for empty cart', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $proof = $this->generator->generateProof($cart);

        expect($proof)->toBeArray()
            ->and($proof['cart_id'])->toBe('cart-123')
            ->and($proof['item_hashes'])->toBeEmpty()
            ->and($proof['merkle_tree'])->toBeEmpty()
            ->and($proof)->toHaveKey('root_hash')
            ->and($proof)->toHaveKey('metadata')
            ->and($proof)->toHaveKey('timestamp')
            ->and($proof)->toHaveKey('signature');
    });

    it('generates proof with items', function (): void {
        $cart = new Cart($this->storage, 'cart-456');
        $cart->add('item-1', 'Product A', 1000, 2);
        $cart->add('item-2', 'Product B', 500, 1);

        $proof = $this->generator->generateProof($cart);

        expect($proof['cart_id'])->toBe('cart-456')
            ->and($proof['item_hashes'])->toHaveCount(2)
            ->and($proof['item_hashes'])->toHaveKey('item-1')
            ->and($proof['item_hashes'])->toHaveKey('item-2')
            ->and($proof['merkle_tree'])->not->toBeEmpty()
            ->and($proof['metadata']['item_count'])->toBe(2)
            ->and($proof['metadata']['total_quantity'])->toBe(3);
    });

    it('generates compact hash', function (): void {
        $cart = new Cart($this->storage, 'cart-789');
        $cart->add('item-1', 'Product', 1000, 1);

        $hash = $this->generator->generateCompactHash($cart);

        expect($hash)->toBeString()
            ->and(strlen($hash))->toBe(64); // SHA256 produces 64 character hex
    });

    it('generates consistent hashes for same cart state', function (): void {
        $cart = new Cart($this->storage, 'cart-test');
        $cart->add('item-1', 'Product', 1000, 1);

        $hash1 = $this->generator->generateCompactHash($cart);
        $hash2 = $this->generator->generateCompactHash($cart);

        expect($hash1)->toBe($hash2);
    });

    it('generates item proof for existing item', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 1000, 1);

        $itemProof = $this->generator->generateItemProof($cart, 'item-1');

        expect($itemProof)->toBeArray()
            ->and($itemProof['item_id'])->toBe('item-1')
            ->and($itemProof['item_hash'])->toBeString()
            ->and($itemProof)->toHaveKey('proof_path')
            ->and($itemProof)->toHaveKey('root_hash');
    });

    it('throws exception for item proof when item does not exist', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $this->generator->generateItemProof($cart, 'non-existent-item');
    })->throws(InvalidArgumentException::class);

    it('generates merkle tree with correct structure', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'A', 100, 1);
        $cart->add('item-2', 'B', 200, 1);
        $cart->add('item-3', 'C', 300, 1);
        $cart->add('item-4', 'D', 400, 1);

        $proof = $this->generator->generateProof($cart);

        expect($proof['merkle_tree'])->not->toBeEmpty()
            ->and($proof['item_hashes'])->toHaveCount(4);
    });

    it('includes signature in proof', function (): void {
        $cart = new Cart($this->storage, 'cart-sig');

        $proof = $this->generator->generateProof($cart);

        expect($proof['signature'])->toBeString()
            ->and(strlen($proof['signature']))->toBe(64); // HMAC-SHA256
    });
});
