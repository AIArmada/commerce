<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Resources\SocialProfileResource\Pages;

use AIArmada\Contacting\Features\SocialProfiles\Actions\UpdateSocialProfileAction;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;
use AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Resources\SocialProfileResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditSocialProfile extends EditRecord
{
    protected static string $resource = SocialProfileResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof SocialProfile, 404);

        return app(UpdateSocialProfileAction::class)->execute($record, $data);
    }
}
