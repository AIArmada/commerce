<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource\Pages;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateAdministration;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateRemoval;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class EditAffiliate extends EditRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->authorize(fn (Affiliate $record): bool => AffiliateResource::canDelete($record))
                ->visible(fn (Affiliate $record): bool => AffiliateResource::canDelete($record))
                ->using(fn (Affiliate $record): bool => app(AffiliateRemoval::class)->delete((string) $record->getKey())),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            app(AffiliateAdministration::class)->update((string) $record->getKey(), $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'data.linked_user' => $exception->getMessage(),
            ]);
        }

        return $record->refresh();
    }
}
