<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Resources\SocialProfileResource\Pages;

use AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Resources\SocialProfileResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSocialProfile extends CreateRecord
{
    protected static string $resource = SocialProfileResource::class;
}
