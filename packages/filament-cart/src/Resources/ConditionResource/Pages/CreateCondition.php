<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\ConditionResource\Pages;

use AIArmada\Cart\Features\Conditions\Actions\SaveStoredCondition;
use AIArmada\Cart\Features\Conditions\Models\Condition;
use AIArmada\FilamentCart\Resources\ConditionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCondition extends CreateRecord
{
    protected static string $resource = ConditionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['rules'] = Condition::normalizeRulesDefinition(
            $data['rules'] ?? null,
            ! empty($data['rules']['factory_keys'] ?? [])
        );

        return $data;
    }

    protected function handleRecordCreation(array $data): Condition
    {
        return app(SaveStoredCondition::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
