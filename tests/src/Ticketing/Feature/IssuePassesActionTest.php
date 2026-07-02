<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Actions\IssuePassesAction;
use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Services\DefaultPassIssuer;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

function createTicketTypeForTest(): TicketType
{
    return TicketType::factory()->create([
        'ticketable_type' => TicketType::class,
        'ticketable_id' => TicketType::factory()->create()->getKey(),
    ]);
}

it('rolls back all passes when issuance fails partway through the issuer loop', function (): void {
    $ticketType = createTicketTypeForTest();
    $countBefore = Pass::count();

    $failingIssuer = new class implements PassIssuerInterface
    {
        public int $callCount = 0;

        public function issuePassesFor(PassIssuanceContext $context): Collection
        {
            $this->callCount++;

            for ($i = 0; $i < $context->quantity; $i++) {
                $pass = new Pass;
                $pass->ticketable_type = 'workshop';
                $pass->ticketable_id = (string) Str::uuid();
                $pass->ticket_type_id = $context->ticketType->getKey();
                $pass->pass_no = 'PASS-FAIL-' . $this->callCount . '-' . $i;
                $pass->status = 'issued';
                $pass->issued_at = $context->issuedAt;
                $pass->save();

                if ($i >= 2) {
                    throw new RuntimeException('Simulated failure after pass ' . $i);
                }
            }

            return new Collection;
        }
    };

    app()->instance(PassIssuerInterface::class, $failingIssuer);

    $context = new PassIssuanceContext(
        ticketType: $ticketType,
        quantity: 5,
    );

    try {
        app(IssuePassesAction::class)->handle($context);
    } catch (RuntimeException) {
        // Expected
    }

    expect(Pass::count())->toBe($countBefore);
});

it('commits all passes on successful issuance', function (): void {
    $ticketType = createTicketTypeForTest();
    $countBefore = Pass::count();

    $passes = new Collection;
    for ($i = 0; $i < 3; $i++) {
        $pass = new Pass;
        $pass->ticketable_type = 'workshop';
        $pass->ticketable_id = (string) Str::uuid();
        $pass->ticket_type_id = $ticketType->getKey();
        $pass->pass_no = 'PASS-SUCCESS-' . $i;
        $pass->status = 'issued';
        $pass->issued_at = now();
        $pass->save();
        $passes->push($pass);
    }

    $issuer = new class($passes) implements PassIssuerInterface
    {
        public function __construct(private Collection $passes) {}

        public function issuePassesFor(PassIssuanceContext $context): Collection
        {
            return $this->passes;
        }
    };

    app()->instance(PassIssuerInterface::class, $issuer);

    $result = app(IssuePassesAction::class)->handle(new PassIssuanceContext(
        ticketType: $ticketType,
        quantity: 3,
    ));

    expect($result)->toHaveCount(3);
    expect(Pass::count())->toBe($countBefore + 3);
});

it('generates pass numbers that are unique across owners', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Pass Number Owner A',
        'email' => 'pass-number-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Pass Number Owner B',
        'email' => 'pass-number-owner-b@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn (): Pass => Pass::factory()->create([
        'pass_no' => 'PASS-DUPLICAT',
    ]));

    $ticketType = createTicketTypeForTest();

    Str::createRandomStringsUsingSequence([
        'DUPLICAT',
        'UNIQUE01',
        'BARCODEVALUE1234',
    ]);

    try {
        $passes = OwnerContext::withOwner($ownerB, fn (): Collection => app(DefaultPassIssuer::class)->issuePassesFor(
            new PassIssuanceContext(
                ticketType: $ticketType,
                quantity: 1,
            )
        ));
    } finally {
        Str::createRandomStringsNormally();
    }

    expect($passes->first()->pass_no)->toBe('PASS-UNIQUE01');
});
