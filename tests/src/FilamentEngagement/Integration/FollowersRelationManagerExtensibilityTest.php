<?php

declare(strict_types=1);

use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\RelationManagers\FollowersRelationManager;
use Illuminate\Database\Eloquent\Builder;

test('filament engagement followers relation manager can be subclassed cleanly', function (): void {
    $reflection = new ReflectionClass(FollowersRelationManager::class);

    $subclass = new class extends FollowersRelationManager
    {
        protected function modifyFollowersQuery(Builder $query): Builder
        {
            return $query;
        }
    };

    expect($reflection->isFinal())->toBeFalse()
        ->and($subclass)->toBeInstanceOf(FollowersRelationManager::class);
});
