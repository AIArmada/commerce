<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Pages;

use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditCreatorPostback extends EditRecord
{
    protected static string $resource = CreatorPostbackResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var CreatorPostbackEndpoint $record */
        $secret = $data['signing_secret'] ?? null;
        unset($data['signing_secret']);

        if (is_string($secret) && $secret !== '') {
            $data['signing_secret_encrypted'] = encrypt($secret);
        }

        $record->update($data);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
