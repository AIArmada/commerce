<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Support\ConditionTargetDisplay;
use AIArmada\FilamentVouchers\Support\ConditionTargetFormData;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherDeletion;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Runtime\VoucherUpdater;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class EditVoucher extends EditRecord
{
    protected static string $resource = VoucherResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Voucher) {
            throw new InvalidArgumentException('Expected a voucher record.');
        }

        return app(VoucherUpdater::class)->handle($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->using(fn (Voucher $record): bool => app(VoucherDeletion::class)->handle((string) $record->getKey())),
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

        return $this->persistConditionTargetDefinition($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateConditionTargetState(array $data): array
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $definition = ConditionTargetDisplay::definition(
            $data['target_definition'] ?? null
        );

        $dsl = ConditionTargetDisplay::dsl($definition);
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
}
