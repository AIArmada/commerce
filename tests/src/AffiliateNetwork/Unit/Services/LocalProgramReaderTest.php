<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\Catalog\LocalProgramReader;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;

function createOwnedProgram(object $owner, string $suffix): AffiliateProgram
{
    return OwnerContext::withOwner($owner, fn (): AffiliateProgram => AffiliateProgram::create([
        'name' => 'Reader Program ' . $suffix,
        'slug' => 'reader-program-' . $suffix . '-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
        'default_commission_rate_basis_points' => 1200,
        'cookie_lifetime_days' => 30,
    ]));
}

function createOwnedSite(object $owner, string $suffix): AffiliateSite
{
    return OwnerContext::withOwner($owner, fn (): AffiliateSite => AffiliateSite::factory()
        ->verified()
        ->forOwner($owner)
        ->create(['domain' => "reader-{$suffix}-" . uniqid() . '.example.com']));
}

beforeEach(function (): void {
    config([
        'affiliate-network.owner.enabled' => true,
        'affiliate-network.owner.include_global' => false,
        'affiliates.owner.enabled' => true,
        'affiliates.owner.include_global' => false,
    ]);

    // Production console has no ambient owner.
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $this->reader = app(LocalProgramReader::class);
});

describe('LocalProgramReader', function (): void {
    test('lists only the site owner programs', function (): void {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $siteA = createOwnedSite($ownerA, 'a');
        $programA = createOwnedProgram($ownerA, 'a');
        createOwnedProgram($ownerB, 'b');

        $ids = $this->reader->programIds($siteA);

        expect($ids)->toBe([(string) $programA->getKey()]);
    });

    test('ignores ambient context and scopes to the site owner', function (): void {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        // Ambient owner differs from the site owner (e.g. scheduled job or
        // an operator session): the site still determines visibility.
        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

        $siteA = createOwnedSite($ownerA, 'a');
        $programA = createOwnedProgram($ownerA, 'a');
        createOwnedProgram($ownerB, 'b');

        expect($this->reader->programIds($siteA))->toBe([(string) $programA->getKey()])
            ->and($this->reader->snapshot($siteA, (string) $programA->getKey())['program_id'])
            ->toBe((string) $programA->getKey());
    });

    test('snapshot resolves the site owner program', function (): void {
        $owner = User::factory()->create();
        $site = createOwnedSite($owner, 'a');
        $program = createOwnedProgram($owner, 'a');

        $snapshot = $this->reader->snapshot($site, (string) $program->getKey());

        expect($snapshot['program_id'])->toBe((string) $program->getKey())
            ->and($snapshot['subjects'])->toBeArray();
    });

    test('snapshot rejects programs owned by another owner', function (): void {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $siteA = createOwnedSite($ownerA, 'a');
        $programB = createOwnedProgram($ownerB, 'b');

        $this->reader->snapshot($siteA, (string) $programB->getKey());
    })->throws(OfferNotFoundException::class);

    test('lists every program when owner scoping is disabled', function (): void {
        config([
            'affiliate-network.owner.enabled' => false,
            'affiliates.owner.enabled' => false,
        ]);

        $site = AffiliateSite::factory()->verified()->create([
            'domain' => 'reader-open-' . uniqid() . '.example.com',
        ]);
        $programA = createOwnedProgram(User::factory()->create(), 'a');
        $programB = createOwnedProgram(User::factory()->create(), 'b');

        $ids = $this->reader->programIds($site);

        expect($ids)->toHaveCount(2)
            ->and($ids)->toContain((string) $programA->getKey(), (string) $programB->getKey());
    });
});
