<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Support\ConditionTargetFormData;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
use AIArmada\Vouchers\Support\VoucherAffiliateOwnershipGuard;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditVoucher extends EditRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        return $this->hydrateConditionTargetState($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        $record = $this->getRecord();
        $data = $this->enforceOwnerOnUpdate($record, $data);
        $data = VoucherAffiliateOwnershipGuard::sanitize($data);

        return $this->persistConditionTargetDefinition($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateConditionTargetState(array $data): array
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $definition = $data['target_definition']
            ?? $metadata['target_definition']
            ?? null;

        if (! is_array($definition)) {
            $definition = ConditionTargetPreset::default()->target()?->toArray();
            if ($definition === null) {
                $definition = ConditionTarget::from(ConditionTargetPreset::default()->dsl())->toArray();
            }
        }

        $dsl = ConditionTarget::from($definition)->toDsl();
        $preset = ConditionTargetPreset::detect($dsl) ?? ConditionTargetPreset::default();

        $data['condition_target_dsl'] = $dsl;
        $data['condition_target_preset'] = $preset->value;
        $data['metadata'] = $metadata;
        $data['target_definition'] = $definition;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function persistConditionTargetDefinition(array $data): array
    {
        return ConditionTargetFormData::persist($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enforceOwnerOnUpdate(Model $record, array $data): array
    {
        if (! config('vouchers.owner.enabled', false)) {
            return $data;
        }

        $existingOwnerType = $record->getAttribute('owner_type');
        $existingOwnerId = $record->getAttribute('owner_id');

        if ($existingOwnerType === null || $existingOwnerId === null) {
            $data['owner_type'] = null;
            $data['owner_id'] = null;

            return $data;
        }

        $data['owner_type'] = (string) $existingOwnerType;
        $data['owner_id'] = (string) $existingOwnerId;

        return $data;
    }
}
