<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages;

use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\ReferralProgramResource;
use AIArmada\FilamentReferrals\Support\ReferralProgramFormData;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

class EditReferralProgram extends EditRecord
{
    protected static string $resource = ReferralProgramResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['referrer_reward'] = ReferralProgramFormData::toForm($data['referrer_reward'] ?? null);
        $data['referee_reward'] = ReferralProgramFormData::toForm($data['referee_reward'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ReferralProgramFormData::toDomain($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $program = app(ReferralProgramGuard::class)->program((string) $record->getKey());
        $program->fill($data);
        $program->save();

        return $program;
    }

    protected function getRedirectUrl(): string
    {
        return ReferralProgramResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
