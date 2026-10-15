<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages;

use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\ReferralProgramResource;
use AIArmada\FilamentReferrals\Support\ReferralProgramFormData;
use AIArmada\Referrals\Actions\UpdateReferralProgram;
use AIArmada\Referrals\Exceptions\ReferralProgramAttributeException;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
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
     * The core update action is the single write path: it re-resolves the
     * program through the guard under a row lock and re-applies the creation
     * rules, so an invalid edit is refused instead of stored. Status and
     * owner tuples are rejected by the action; the status select is also
     * non-dehydrated on edit, so a tampered payload cannot move the program.
     *
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return UpdateReferralProgram::run((string) $record->getKey(), $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                self::validationKey($exception) => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Attaches a core rejection to the form field the operator can fix. The
     * normalizer tags the offending attribute; immutable attributes (status,
     * owner tuples) have no field of their own, so they fall back to the
     * program name to keep the refusal visible.
     */
    private static function validationKey(InvalidArgumentException $exception): string
    {
        $attribute = $exception instanceof ReferralProgramAttributeException
            ? $exception->attribute
            : 'name';

        if ($attribute === 'status' || str_starts_with($attribute, 'owner_')) {
            $attribute = 'name';
        }

        return 'data.' . $attribute;
    }

    protected function getRedirectUrl(): string
    {
        return ReferralProgramResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
