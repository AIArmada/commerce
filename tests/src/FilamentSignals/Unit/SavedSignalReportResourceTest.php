<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentSignals\Resources\SavedSignalReportResource;
use AIArmada\Signals\Models\SavedSignalReport;

uses(FilamentSignalsTestCase::class);

it('scopes saved signal reports to the current owner', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Saved Report Owner A',
        'email' => 'saved-report-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Saved Report Owner B',
        'email' => 'saved-report-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $reportA = SavedSignalReport::query()->create([
        'name' => 'Owner A Report',
        'slug' => 'owner-a-report',
        'report_type' => 'page_views',
    ]);
    $reportA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $reportB = SavedSignalReport::query()->create([
        'name' => 'Owner B Report',
        'slug' => 'owner-b-report',
        'report_type' => 'live_activity',
    ]);
    $reportB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $query = SavedSignalReportResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()?->id)->toBe($reportA->id);
});
