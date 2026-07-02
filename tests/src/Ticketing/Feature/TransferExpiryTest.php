<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Actions\TransferPassToHolderAction;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;

it('blocks transfer past transfer_expires_at', function () {
    $pass = Pass::factory()->create([
        'transfer_expires_at' => now()->subDay(),
    ]);

    expect(fn () => app(TransferPassToHolderAction::class)->handle(
        $pass,
        PassHolder::factory()->make()
    ))->toThrow(RuntimeException::class);
});

it('expires transfer windows via command', function () {
    $past = now()->subDays(2);
    $future = now()->addDays(2);

    $expiredPass = Pass::factory()->create(['transfer_expires_at' => $past]);
    $activePass = Pass::factory()->create(['transfer_expires_at' => $future]);

    $this->artisan('ticketing:expire-transfers')
        ->assertExitCode(0);

    $expiredPass->refresh();
    expect($expiredPass->transfer_expires_at)->toBeNull();

    $activePass->refresh();
    expect($activePass->transfer_expires_at)->not->toBeNull();
});

it('expires transfer windows across owners', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Ticketing Owner A',
        'email' => 'ticketing-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Ticketing Owner B',
        'email' => 'ticketing-owner-b@example.com',
        'password' => 'secret',
    ]);

    $expiredA = OwnerContext::withOwner($ownerA, fn (): Pass => Pass::factory()->create([
        'transfer_expires_at' => now()->subDays(2),
    ]));
    $expiredB = OwnerContext::withOwner($ownerB, fn (): Pass => Pass::factory()->create([
        'transfer_expires_at' => now()->subDays(2),
    ]));
    $activeB = OwnerContext::withOwner($ownerB, fn (): Pass => Pass::factory()->create([
        'transfer_expires_at' => now()->addDays(2),
    ]));

    $this->artisan('ticketing:expire-transfers')
        ->assertExitCode(0);

    OwnerContext::withOwner(null, function () use ($expiredA, $expiredB, $activeB): void {
        expect(Pass::query()->withoutOwnerScope()->findOrFail($expiredA->id)->transfer_expires_at)->toBeNull()
            ->and(Pass::query()->withoutOwnerScope()->findOrFail($expiredB->id)->transfer_expires_at)->toBeNull()
            ->and(Pass::query()->withoutOwnerScope()->findOrFail($activeB->id)->transfer_expires_at)->not->toBeNull();
    });
});
