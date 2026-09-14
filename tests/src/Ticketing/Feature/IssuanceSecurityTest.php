<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Actions\IssuePassesAction;
use AIArmada\Ticketing\Actions\TransferPassToHolderAction;
use AIArmada\Ticketing\Exceptions\IssuanceQuantityExceededException;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Services\DefaultPassIssuer;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

function createIssuanceTicketType(): TicketType
{
    return TicketType::factory()->create([
        'ticketable_type' => TicketType::class,
        'ticketable_id' => TicketType::factory()->create()->getKey(),
    ]);
}

it('rejects issuance above the configured maximum', function (): void {
    config()->set('ticketing.issuance.max_quantity', 3);

    expect(fn () => app(DefaultPassIssuer::class)->issuePassesFor(new PassIssuanceContext(
        ticketType: createIssuanceTicketType(),
        quantity: 4,
    )))->toThrow(IssuanceQuantityExceededException::class);
});

it('issues batches up to the configured maximum', function (): void {
    config()->set('ticketing.issuance.max_quantity', 3);

    $passes = app(DefaultPassIssuer::class)->issuePassesFor(new PassIssuanceContext(
        ticketType: createIssuanceTicketType(),
        quantity: 3,
    ));

    expect($passes)->toHaveCount(3)
        ->and(Pass::query()->whereIn('id', $passes->modelKeys())->count())->toBe(3);
});

it('regenerates colliding qr codes instead of failing', function (): void {
    $existing = Pass::factory()->create(['qr_code' => 'QR-COLLIDE-VALUE']);
    $ticketType = createIssuanceTicketType();

    Str::createUuidsUsingSequence(['QR-COLLIDE-VALUE', 'QR-FRESH-VALUE']);

    try {
        $passes = app(DefaultPassIssuer::class)->issuePassesFor(new PassIssuanceContext(
            ticketType: $ticketType,
            quantity: 1,
        ));
    } finally {
        Str::createUuidsNormally();
    }

    expect($passes->first()->qr_code)->toBe('QR-FRESH-VALUE')
        ->and($existing->fresh()?->qr_code)->toBe('QR-COLLIDE-VALUE');
});

it('rejects holder attributes with an invalid email', function (): void {
    expect(fn () => app(IssuePassesAction::class)->handle(new PassIssuanceContext(
        ticketType: createIssuanceTicketType(),
        quantity: 1,
        holderAttributes: ['name' => 'Bad Email', 'email' => 'not-an-email'],
    )))->toThrow(InvalidArgumentException::class, 'email');
});

it('rejects holder attributes with an overlong name', function (): void {
    expect(fn () => app(IssuePassesAction::class)->handle(new PassIssuanceContext(
        ticketType: createIssuanceTicketType(),
        quantity: 1,
        holderAttributes: ['name' => str_repeat('a', 256)],
    )))->toThrow(InvalidArgumentException::class, 'name');
});

it('rejects holder references missing one side of the morph pair', function (): void {
    expect(fn () => app(IssuePassesAction::class)->handle(new PassIssuanceContext(
        ticketType: createIssuanceTicketType(),
        quantity: 1,
        holderAttributes: ['holder_type' => User::class],
    )))->toThrow(InvalidArgumentException::class, 'together');
});

it('rejects holder types that do not resolve to a model', function (): void {
    expect(fn () => app(IssuePassesAction::class)->handle(new PassIssuanceContext(
        ticketType: createIssuanceTicketType(),
        quantity: 1,
        holderAttributes: ['holder_type' => 'not-a-model', 'holder_id' => (string) Str::uuid()],
    )))->toThrow(InvalidArgumentException::class, 'model');
});

it('rejects holder types outside the configured allow-list', function (): void {
    config()->set('ticketing.holders.allowed_types', [User::class]);

    expect(fn () => app(IssuePassesAction::class)->handle(new PassIssuanceContext(
        ticketType: createIssuanceTicketType(),
        quantity: 1,
        holderAttributes: ['holder_type' => Pass::class, 'holder_id' => Pass::factory()->create()->getKey()],
    )))->toThrow(InvalidArgumentException::class, 'not allowed');
});

it('rejects holder links to rows outside the current owner scope', function (): void {
    $ownerA = User::query()->create(['name' => 'Scope A', 'email' => 'scope-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Scope B', 'email' => 'scope-b@example.com', 'password' => 'secret']);

    $foreignPass = OwnerContext::withOwner($ownerB, fn (): Pass => Pass::factory()->create());

    expect(fn () => OwnerContext::withOwner($ownerA, fn () => app(IssuePassesAction::class)->handle(
        new PassIssuanceContext(
            ticketType: createIssuanceTicketType(),
            quantity: 1,
            holderAttributes: ['holder_type' => $foreignPass->getMorphClass(), 'holder_id' => $foreignPass->getKey()],
        )
    )))->toThrow(AuthorizationException::class);
});

it('accepts holder links within the current owner scope', function (): void {
    $owner = User::query()->create(['name' => 'Scope C', 'email' => 'scope-c@example.com', 'password' => 'secret']);

    $passes = OwnerContext::withOwner($owner, function (): Collection {
        $linked = Pass::factory()->create();

        return app(IssuePassesAction::class)->handle(new PassIssuanceContext(
            ticketType: createIssuanceTicketType(),
            quantity: 1,
            holderAttributes: ['holder_type' => $linked->getMorphClass(), 'holder_id' => $linked->getKey()],
        ));
    });

    expect($passes)->toHaveCount(1);
});

it('rejects invalid holder emails on the transfer path', function (): void {
    $pass = Pass::factory()->create();

    expect(fn () => app(TransferPassToHolderAction::class)->handle(
        $pass,
        null,
        ['name' => 'Bad Email', 'email' => 'not-an-email'],
    ))->toThrow(InvalidArgumentException::class, 'email');
});
