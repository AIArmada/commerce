<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources\SocialProfileResource\Pages;

use AIArmada\FilamentContacting\Resources\SocialProfileResource;
use Filament\Resources\Pages\EditRecord;

final class EditSocialProfile extends EditRecord
{
    protected static string $resource = SocialProfileResource::class;
}
