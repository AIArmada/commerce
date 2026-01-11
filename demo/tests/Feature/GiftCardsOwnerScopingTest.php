<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only gift cards for the current owner context', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $cardA = OwnerContext::withOwner($ownerA, function () use ($ownerA): GiftCard {
        return GiftCard::create([
            'code' => 'GC-OWNER-A-0001',
            'status' => 'active',
            'currency' => 'MYR',
            'initial_balance' => 10_000,
            'current_balance' => 10_000,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]);
    });

    $cardB = OwnerContext::withOwner($ownerB, function () use ($ownerB): GiftCard {
        return GiftCard::create([
            'code' => 'GC-OWNER-B-0001',
            'status' => 'active',
            'currency' => 'MYR',
            'initial_balance' => 20_000,
            'current_balance' => 20_000,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);
    });

    OwnerContext::withOwner($ownerA, function () use ($cardA, $cardB): void {
        $this->get('/gift-cards')
            ->assertOk()
            ->assertSee($cardA->code)
            ->assertDontSee($cardB->code);
    });
});

it('does not allow checking another owner\'s gift card by code', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        GiftCard::create([
            'code' => 'GC-OWNER-A-LOCKED',
            'status' => 'active',
            'currency' => 'MYR',
            'initial_balance' => 15_000,
            'current_balance' => 15_000,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]);
    });

    OwnerContext::withOwner($ownerB, function (): void {
        $this->get('/gift-cards?code=GC-OWNER-A-LOCKED')
            ->assertOk()
            ->assertSee('Gift card not found');
    });
});
