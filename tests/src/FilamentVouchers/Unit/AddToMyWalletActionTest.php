<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentVouchers\Actions\AddToMyWalletAction;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\States\Active;

uses(TestCase::class);

it('uses voucher code when adding to wallet via service fallback', function (): void {
    $user = User::query()->create([
        'name' => 'Wallet Action User',
        'email' => 'wallet-action-user@example.com',
        'password' => 'secret',
    ]);

    test()->actingAs($user);

    $voucher = Voucher::query()->create([
        'code' => 'WALLET-ACTION-CODE',
        'name' => 'Wallet Action Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => Active::class,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $fakeService = new class
    {
        public ?string $code = null;

        public mixed $holder = null;

        /** @var array<string, mixed>|null */
        public ?array $metadata = null;

        /**
         * @param  array<string, mixed>|null  $metadata
         */
        public function addToWallet(string $code, mixed $holder, ?array $metadata = null): void
        {
            $this->code = $code;
            $this->holder = $holder;
            $this->metadata = $metadata;
        }
    };

    app()->instance(VoucherService::class, $fakeService);

    $action = AddToMyWalletAction::make();
    $handler = $action->record($voucher)->getActionFunction();

    expect($handler)->not->toBeNull();

    $handler([
        'notes' => 'Remember for checkout',
    ], $voucher);

    expect($fakeService->code)->toBe('WALLET-ACTION-CODE')
        ->and((string) $fakeService->holder?->getKey())->toBe((string) $user->getKey())
        ->and($fakeService->metadata)->toBe(['notes' => 'Remember for checkout']);
});

it('does not add cross-owner voucher to wallet when owner scoping is enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    $user = User::query()->create([
        'name' => 'Scoped Wallet User',
        'email' => 'scoped-wallet-user@example.com',
        'password' => 'secret',
    ]);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'wallet-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'wallet-owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $voucherOwnedByB = OwnerContext::withOwner($ownerB, static fn (): Voucher => Voucher::query()->create([
        'code' => 'WALLET-OTHER-OWNER',
        'name' => 'Other Owner Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => Active::class,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]));

    test()->actingAs($user);

    $fakeService = new class
    {
        public ?string $code = null;

        public mixed $holder = null;

        /** @var array<string, mixed>|null */
        public ?array $metadata = null;

        /**
         * @param  array<string, mixed>|null  $metadata
         */
        public function addToWallet(string $code, mixed $holder, ?array $metadata = null): void
        {
            $this->code = $code;
            $this->holder = $holder;
            $this->metadata = $metadata;
        }
    };

    app()->instance(VoucherService::class, $fakeService);

    $action = AddToMyWalletAction::make();
    $handler = $action->record($voucherOwnedByB)->getActionFunction();

    expect($handler)->not->toBeNull();

    $handler([
        'notes' => 'Should be blocked',
    ], $voucherOwnedByB);

    expect($fakeService->code)->toBeNull();
});
