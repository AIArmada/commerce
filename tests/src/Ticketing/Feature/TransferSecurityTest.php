<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Actions\BulkTransferPassesAction;
use AIArmada\Ticketing\Actions\TransferPassToHolderAction;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Models\PassTransfer;
use Illuminate\Auth\Access\AuthorizationException;

function createUserHolder(string $name, string $email): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $email,
        'password' => 'secret',
    ]);
}

function linkPassToUser(Pass $pass, User $user): PassHolder
{
    return PassHolder::factory()->create([
        'pass_id' => $pass->getKey(),
        'holder_type' => $user->getMorphClass(),
        'holder_id' => $user->getKey(),
        'name' => $user->name,
        'email' => $user->email,
        'is_current' => true,
    ]);
}

it('authorizes the current holder when an acting user is given', function (): void {
    $owner = createUserHolder('Holder A', 'holder-a@example.com');
    $pass = Pass::factory()->create();
    linkPassToUser($pass, $owner);

    $result = app(TransferPassToHolderAction::class)->handle(
        $pass,
        PassHolder::factory()->make(['name' => 'Recipient', 'email' => 'recipient@example.com']),
        authorizedBy: $owner,
    );

    expect($result->name)->toBe('Recipient');
});

it('denies transfer for a different user than the current holder', function (): void {
    $owner = createUserHolder('Holder B', 'holder-b@example.com');
    $intruder = createUserHolder('Intruder', 'intruder@example.com');
    $pass = Pass::factory()->create();
    linkPassToUser($pass, $owner);

    expect(fn () => app(TransferPassToHolderAction::class)->handle(
        $pass,
        PassHolder::factory()->make(),
        authorizedBy: $intruder,
    ))->toThrow(AuthorizationException::class);
});

it('denies expired passes when an acting user is given', function (): void {
    $owner = createUserHolder('Holder C', 'holder-c@example.com');
    $pass = Pass::factory()->create(['transfer_expires_at' => now()->subDay()]);
    linkPassToUser($pass, $owner);

    expect(fn () => app(TransferPassToHolderAction::class)->handle(
        $pass,
        PassHolder::factory()->make(),
        authorizedBy: $owner,
    ))->toThrow(AuthorizationException::class);
});

it('allows machine transfers without an acting user', function (): void {
    $pass = Pass::factory()->create();

    $result = app(TransferPassToHolderAction::class)->handle(
        $pass,
        PassHolder::factory()->make(['name' => 'Machine Recipient']),
    );

    expect($result->name)->toBe('Machine Recipient');
});

it('rejects a persisted holder that belongs to another pass', function (): void {
    $passA = Pass::factory()->create();
    $passB = Pass::factory()->create();
    $holderOfA = PassHolder::factory()->create(['pass_id' => $passA->getKey()]);

    expect(fn () => app(TransferPassToHolderAction::class)->handle($passB, $holderOfA))
        ->toThrow(InvalidArgumentException::class, 'different pass');

    expect($holderOfA->fresh()?->pass_id)->toBe($passA->getKey());
});

it('leaves no orphan holder when the transfer fails', function (): void {
    $pass = Pass::factory()->create(['transfer_expires_at' => now()->subDay()]);
    $holdersBefore = PassHolder::query()->count();

    try {
        app(TransferPassToHolderAction::class)->handle(
            $pass,
            null,
            ['name' => 'Orphan', 'email' => 'orphan@example.com'],
        );
        $this->fail('Expected transfer to fail.');
    } catch (RuntimeException) {
        // Expected: pass cannot transfer past its window.
    }

    expect(PassHolder::query()->count())->toBe($holdersBefore);
});

it('gives every bulk-transferred pass its own holder row', function (): void {
    $passes = Pass::factory()->count(3)->create();

    $results = app(BulkTransferPassesAction::class)->handle(
        $passes->pluck('id')->all(),
        PassHolder::factory()->make(['name' => 'Bulk Recipient', 'email' => 'bulk-recipient@example.com']),
    );

    expect($results)->toHaveCount(3)
        ->and($results->pluck('id')->unique())->toHaveCount(3);

    foreach ($passes as $pass) {
        $current = PassHolder::query()
            ->where('pass_id', $pass->getKey())
            ->where('is_current', true)
            ->sole();

        expect($current->name)->toBe('Bulk Recipient');
    }
});

it('rejects bulk batches that mix owners', function (): void {
    $ownerA = createUserHolder('Bulk Mix A', 'bulk-mix-a@example.com');
    $ownerB = createUserHolder('Bulk Mix B', 'bulk-mix-b@example.com');

    $passA = OwnerContext::withOwner($ownerA, fn (): Pass => Pass::factory()->create());
    $passB = OwnerContext::withOwner($ownerB, fn (): Pass => Pass::factory()->create());

    // Owner scoping snapshots at model boot; reboot models so the disabled
    // flag takes effect, then restore boot state for subsequent tests.
    config()->set('ticketing.owner.enabled', false);
    Pass::clearBootedModels();

    try {
        expect(fn () => app(BulkTransferPassesAction::class)->handle(
            [$passA->id, $passB->id],
            PassHolder::factory()->make(),
        ))->toThrow(AuthorizationException::class, 'single owner');
    } finally {
        Pass::clearBootedModels();
    }
});

it('leaves exactly one current holder with a correct from/to record', function (): void {
    $owner = createUserHolder('Current A', 'current-a@example.com');
    $pass = Pass::factory()->create();
    $oldHolder = linkPassToUser($pass, $owner);
    $newUser = createUserHolder('Current B', 'current-b@example.com');

    $result = app(TransferPassToHolderAction::class)->handle($pass, $newUser);

    $currents = PassHolder::query()
        ->where('pass_id', $pass->getKey())
        ->where('is_current', true)
        ->get();

    expect($currents)->toHaveCount(1)
        ->and($currents->sole()->getKey())->toBe($result->getKey())
        ->and($result->transferred_at)->toBeNull()
        ->and($oldHolder->fresh()?->is_current)->toBeFalse()
        ->and($oldHolder->fresh()?->transferred_at)->not->toBeNull();

    $transfer = PassTransfer::query()->where('pass_id', $pass->getKey())->sole();

    expect($transfer->from_holder_id)->toBe($oldHolder->getKey())
        ->and($transfer->to_holder_id)->toBe($result->getKey());
});

it('binds the new holder and transfer record to the pass owner', function (): void {
    $owner = createUserHolder('Pass Owner', 'pass-owner@example.com');
    $pass = OwnerContext::withOwner($owner, fn (): Pass => Pass::factory()->create());
    $newUser = createUserHolder('Owner Inherit', 'owner-inherit@example.com');

    $result = OwnerContext::withOwner($owner, fn (): PassHolder => app(TransferPassToHolderAction::class)->handle($pass, $newUser));

    expect($result->owner_type)->toBe($pass->owner_type)
        ->and((string) $result->owner_id)->toBe((string) $pass->owner_id);

    $transfer = PassTransfer::query()->withoutGlobalScopes()->where('pass_id', $pass->getKey())->sole();

    expect($transfer->owner_type)->toBe($pass->owner_type)
        ->and((string) $transfer->owner_id)->toBe((string) $pass->owner_id);
});

it('fails closed when transferring an owned pass outside its owner scope', function (): void {
    $owner = createUserHolder('Scope Owner', 'scope-owner@example.com');
    $pass = OwnerContext::withOwner($owner, fn (): Pass => Pass::factory()->create());
    $newUser = createUserHolder('Scope Stranger', 'scope-stranger@example.com');

    // The default test owner context does not match the pass owner.
    expect(fn () => app(TransferPassToHolderAction::class)->handle($pass, $newUser))
        ->toThrow(AuthorizationException::class);
});

it('rejects model holders outside the configured allow-list', function (): void {
    $allowedUser = createUserHolder('Allow A', 'allow-a@example.com');
    config()->set('ticketing.holders.allowed_types', [$allowedUser->getMorphClass()]);

    $pass = Pass::factory()->create();
    $otherPass = Pass::factory()->create();

    expect(fn () => app(TransferPassToHolderAction::class)->handle($pass, $otherPass))
        ->toThrow(InvalidArgumentException::class, 'not allowed');
});
