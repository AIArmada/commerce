<?php

declare(strict_types=1);

use AIArmada\Cart\Snapshots\SyncNormalizedCartJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;

it('coalesces sync jobs per owner cart identity', function (): void {
    $first = new SyncNormalizedCartJob('guest-1', 'default', 'App\\Models\\User', 7);
    $duplicate = new SyncNormalizedCartJob('guest-1', 'default', 'App\\Models\\User', 7);
    $otherCart = new SyncNormalizedCartJob('guest-2', 'default', 'App\\Models\\User', 7);
    $global = new SyncNormalizedCartJob('guest-1', 'default', null, null, true);

    expect($first)->toBeInstanceOf(ShouldBeUnique::class);
    expect($duplicate->uniqueId())->toBe($first->uniqueId());
    expect($otherCart->uniqueId())->not->toBe($first->uniqueId());
    expect($global->uniqueId())->not->toBe($first->uniqueId());
    expect($first->uniqueFor())->toBeGreaterThan(0);
});
