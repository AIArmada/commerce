<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('archives expired published offers without touching drafts or recent offers', function (): void {
    $site = AffiliateSite::factory()->verified()->create();

    $expiredPublished = AffiliateOffer::factory()
        ->forSite($site)
        ->published()
        ->create(['ends_at' => CarbonImmutable::now()->subDays(91)]);

    $expiredDraft = AffiliateOffer::factory()
        ->forSite($site)
        ->draft()
        ->create(['ends_at' => CarbonImmutable::now()->subDays(91)]);

    $recentPublished = AffiliateOffer::factory()
        ->forSite($site)
        ->published()
        ->create(['ends_at' => CarbonImmutable::now()->subDays(30)]);

    $futurePublished = AffiliateOffer::factory()
        ->forSite($site)
        ->published()
        ->create(['ends_at' => CarbonImmutable::now()->addDay()]);

    $this->artisan('affiliate-network:archive-expired')
        ->assertExitCode(Command::SUCCESS);

    expect($expiredPublished->fresh()->status)->toBe(OfferStatus::Archived)
        ->and($expiredPublished->fresh()->archived_at)->not->toBeNull()
        ->and($expiredDraft->fresh()->status)->toBe(OfferStatus::Draft)
        ->and($recentPublished->fresh()->status)->toBe(OfferStatus::Published)
        ->and($futurePublished->fresh()->status)->toBe(OfferStatus::Published);
});

it('clamps a negative older-than option to zero', function (): void {
    $site = AffiliateSite::factory()->verified()->create();

    $expiredPublished = AffiliateOffer::factory()
        ->forSite($site)
        ->published()
        ->create(['ends_at' => CarbonImmutable::now()->subDay()]);

    $this->artisan('affiliate-network:archive-expired --older-than=-5')
        ->assertExitCode(Command::SUCCESS);

    expect($expiredPublished->fresh()->status)->toBe(OfferStatus::Archived);
});

it('archives per owner site when owner scoping is enabled', function (): void {
    config([
        'affiliate-network.owner.enabled' => true,
        'affiliate-network.owner.include_global' => false,
    ]);

    // Production console has no ambient owner; drop the testbench default
    // resolver so the command exercises owner discovery (the owner-discovery crash).
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $offers = [];

    foreach ([$ownerA, $ownerB] as $index => $owner) {
        $offers[] = OwnerContext::withOwner($owner, function () use ($owner, $index): AffiliateOffer {
            $site = AffiliateSite::factory()->verified()->forOwner($owner)->create([
                'domain' => "archive-{$index}-" . uniqid() . '.example.com',
            ]);

            return AffiliateOffer::factory()
                ->published()
                ->forSite($site)
                ->create(['ends_at' => CarbonImmutable::now()->subDays(91)]);
        });
    }

    $this->artisan('affiliate-network:archive-expired')
        ->assertExitCode(Command::SUCCESS);

    // No ambient owner remains, so assert through the unscoped table.
    foreach ($offers as $offer) {
        $row = DB::table($offer->getTable())->where('id', $offer->id)->first();

        expect($row->status)->toBe(OfferStatus::Archived->value);
        expect($row->archived_at)->not->toBeNull();
    }
});

it('reports archive candidates without persisting during dry runs', function (): void {
    $site = AffiliateSite::factory()->verified()->create();

    $expiredPublished = AffiliateOffer::factory()
        ->forSite($site)
        ->published()
        ->create(['ends_at' => CarbonImmutable::now()->subDays(91)]);

    $this->artisan('affiliate-network:archive-expired --dry-run')
        ->expectsOutput('DRY RUN: Archiving expired offers...')
        ->expectsOutput('Offers archived: 1')
        ->assertExitCode(Command::SUCCESS);

    expect($expiredPublished->fresh()->status)->toBe(OfferStatus::Published);
});
