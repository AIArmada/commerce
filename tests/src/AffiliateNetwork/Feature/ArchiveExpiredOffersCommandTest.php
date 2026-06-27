<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

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
        ->and($expiredDraft->fresh()->status)->toBe(OfferStatus::Draft)
        ->and($recentPublished->fresh()->status)->toBe(OfferStatus::Published)
        ->and($futurePublished->fresh()->status)->toBe(OfferStatus::Published);
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
