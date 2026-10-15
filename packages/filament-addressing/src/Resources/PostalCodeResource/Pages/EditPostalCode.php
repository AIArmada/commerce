<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages;

use AIArmada\Addressing\Actions\SavePostalCodeAction;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditPostalCode extends EditRecord
{
    protected static string $resource = PostalCodeResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! PostalCodeResource::isReadOnly();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if (! $record instanceof PostalCode) {
            return $data;
        }

        $data['manual_coverage'] = ModelResolver::areaPostalCodeClass()::query()
            ->where('postal_code_id', $record->getKey())
            ->where('source', 'manual')
            ->whereNull('source_id')
            ->get(['address_area_id', 'relationship_type', 'is_primary'])
            ->map(static fn (AddressAreaPostalCode $link): array => [
                'area_id' => (string) $link->address_area_id,
                'relationship_type' => $link->relationship_type,
                'is_primary' => $link->is_primary,
            ])
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof PostalCode) {
            throw new LogicException('Expected a PostalCode record.');
        }

        return app(SavePostalCodeAction::class)->execute($data, $record);
    }
}
