<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Pages;

use AIArmada\AffiliateNetwork\Features\Postbacks\Api\PostbacksApi;
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
        app(PostbacksApi::class)->update((string) $record->getKey(), (string) $record->affiliate_id, $data);

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->action(fn (CreatorPostbackEndpoint $record) => app(PostbacksApi::class)->delete((string) $record->getKey())),
        ];
    }
}
