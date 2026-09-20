<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;

function createSyncableProgram(string $suffix): AffiliateProgram
{
    $program = AffiliateProgram::create([
        'name' => 'Sync Program ' . $suffix,
        'slug' => 'sync-program-' . $suffix . '-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
        'default_commission_rate_basis_points' => 1200,
        'cookie_lifetime_days' => 30,
    ]);

    AffiliateCommissionRule::create([
        'program_id' => $program->getKey(),
        'name' => 'Sync rule ' . $suffix,
        'rule_type' => CommissionRuleType::Product,
        'priority' => 90,
        'conditions' => ['product_id' => ['in' => ['SYNC-' . $suffix]]],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 2000,
        'is_active' => true,
    ]);

    return $program;
}

it('syncs a program catalog for a site by domain', function (): void {
    $site = AffiliateSite::factory()->verified()->create([
        'domain' => 'sync-' . uniqid() . '.example.com',
    ]);
    $program = createSyncableProgram('A');

    $this->artisan("affiliate-network:sync-offers {$site->domain} --program={$program->getKey()}")
        ->expectsOutput('Offers synced: 1 created, 0 updated, 0 skipped, 0 locked, 0 failed.')
        ->assertExitCode(Command::SUCCESS);

    $offer = AffiliateOffer::query()->where('site_id', $site->getKey())->first();

    expect($offer)->not->toBeNull()
        ->and($offer->external_program_id)->toBe((string) $program->getKey())
        ->and($offer->subject_key)->toBe('SYNC-A')
        ->and($site->fresh()->sync_status)->toBe('ok');
});

it('syncs inside the site owner context when owner scoping is enabled', function (): void {
    config([
        'affiliate-network.owner.enabled' => true,
        'affiliate-network.owner.include_global' => false,
        'affiliates.owner.enabled' => true,
        'affiliates.owner.include_global' => false,
    ]);

    // Production console has no ambient owner; drop the testbench default
    // resolver so the command must enter the site owner context itself.
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $owner = User::factory()->create();

    [$site, $program] = OwnerContext::withOwner($owner, function () use ($owner): array {
        $site = AffiliateSite::factory()->verified()->forOwner($owner)->create([
            'domain' => 'sync-owned-' . uniqid() . '.example.com',
        ]);

        return [$site, createSyncableProgram('B')];
    });

    $this->artisan("affiliate-network:sync-offers {$site->getKey()} --program={$program->getKey()}")
        ->assertExitCode(Command::SUCCESS);

    // No ambient owner remains, so assert through the unscoped model.
    $offer = AffiliateOffer::withoutOwnerScope()->where('site_id', $site->getKey())->first();
    $siteRow = AffiliateSite::withoutOwnerScope()->whereKey($site->getKey())->first();

    expect($offer)->not->toBeNull()
        ->and($offer->external_program_id)->toBe((string) $program->getKey())
        ->and($siteRow->sync_status)->toBe('ok');
});

it('fails cleanly for unknown sites', function (): void {
    $this->artisan('affiliate-network:sync-offers no-such-site.example.com')
        ->assertExitCode(Command::FAILURE);
});
