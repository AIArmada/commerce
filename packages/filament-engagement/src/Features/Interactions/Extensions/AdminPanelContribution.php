<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Interactions\Extensions;

use AIArmada\FilamentEngagement\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkCollectionResource;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkResource;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\FollowResource;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\ReactionResource;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\ResponseResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return array_filter([
            0 => config('filament-engagement.resources.enabled.follow', true) ? FollowResource::class : null,
            1 => config('filament-engagement.resources.enabled.bookmark', true) ? BookmarkResource::class : null,
            2 => config('filament-engagement.resources.enabled.collection', true) ? BookmarkCollectionResource::class : null,
            3 => config('filament-engagement.resources.enabled.response', true) ? ResponseResource::class : null,
            4 => config('filament-engagement.resources.enabled.reaction', true) ? ReactionResource::class : null,
        ], static fn (?string $class): bool => $class !== null);
    }

    public function widgets(): array
    {
        return [];
    }
}
