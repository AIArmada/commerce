<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages;

use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\ReferralProgramResource;
use AIArmada\FilamentReferrals\Support\ReferralProgramFormData;
use AIArmada\Referrals\Actions\CreateReferralProgram as CreateReferralProgramAction;
use AIArmada\Referrals\Models\ReferralProgram;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

class CreateReferralProgram extends CreateRecord
{
    protected static string $resource = ReferralProgramResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ReferralProgramFormData::toDomain($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        return CreateReferralProgramAction::run($data);
    }

    protected function getRedirectUrl(): string
    {
        /** @var ReferralProgram $record */
        $record = $this->getRecord();

        return ReferralProgramResource::getUrl('view', ['record' => $record]);
    }
}
