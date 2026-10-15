<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardProgramResource\Pages;

use AIArmada\FilamentRewards\Resources\RewardProgramResource\RewardProgramResource;
use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use AIArmada\Rewards\Actions\CreateRewardProgramAction;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Models\RewardProgram;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateRewardProgram extends CreateRecord
{
    protected static string $resource = RewardProgramResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(RewardAdminPresenter::staffActor() !== null, 403);

        try {
            $program = CreateRewardProgramAction::run(
                (string) ($data['family_key'] ?? ''),
                (string) ($data['title'] ?? ''),
                RewardProgramMode::from((string) ($data['mode'] ?? '')),
                self::resolveOwner($data),
                null,
                (string) ($data['purpose'] ?? ''),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                self::validationKey($exception) => $exception->getMessage(),
            ]);
        }

        return $program;
    }

    /**
     * Resolve the submitted owner alias against the panel's configured owner
     * types and load the referenced record server-side. The form's Select only
     * presents configured aliases; the handler never trusts the submitted
     * alias or id, and hands the action a Model so it stores the morph class
     * (never the config alias) as `owner_type`.
     *
     * @param  array<string, mixed>  $data
     */
    private static function resolveOwner(array $data): ?Model
    {
        $ownerType = (string) ($data['owner_type'] ?? 'platform');

        if ($ownerType === 'platform') {
            return null;
        }

        $types = config('filament-rewards.owners.types', []);
        $model = is_array($types) && is_array($types[$ownerType] ?? null) ? ($types[$ownerType]['model'] ?? null) : null;

        if (! is_string($model) || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            throw ValidationException::withMessages([
                'owner_record_id' => 'The selected owner type is not configured for this panel.',
            ]);
        }

        $ownerId = (string) ($data['owner_record_id'] ?? '');
        $owner = $ownerId === '' ? null : $model::query()->whereKey($ownerId)->first();

        if (! $owner instanceof Model) {
            throw ValidationException::withMessages([
                'owner_record_id' => 'The selected owner record could not be found.',
            ]);
        }

        return $owner;
    }

    private static function validationKey(InvalidArgumentException $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_starts_with($message, 'Family key '),
            str_starts_with($message, 'Program family already exists.') => 'family_key',
            str_starts_with($message, 'Owned programs require') => 'owner_record_id',
            default => 'data',
        };
    }

    protected function getRedirectUrl(): string
    {
        /** @var RewardProgram $record */
        $record = $this->getRecord();

        return RewardProgramResource::getUrl('view', ['record' => $record]);
    }
}
