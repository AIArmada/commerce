<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages;

use AIArmada\AffiliateNetwork\Features\Sites\Actions\RotateCatalogToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\UpdateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Actions\SyncSiteCatalog;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAffiliateSite extends EditRecord
{
    protected static string $resource = AffiliateSiteResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var AffiliateSite $record */
        $owner = OwnerContext::fromTypeAndId($record->owner_type, $record->owner_id);

        return $this->record = OwnerContext::withOwner($owner, fn (): Model => app(UpdateSite::class)->execute($record, $data));
    }

    protected function getHeaderActions(): array
    {
        return [
            SyncSiteCatalog::make(),
            Actions\Action::make('rotate_catalog_token')
                ->label('Rotate catalog token')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('The previous token stops working immediately. Copy the new token now — it is shown once.')
                ->action(function (AffiliateSite $record): void {
                    $owner = OwnerContext::fromTypeAndId($record->owner_type, $record->owner_id);

                    $token = OwnerContext::withOwner($owner, fn (): string => app(RotateCatalogToken::class)->execute($record));

                    Notification::make()
                        ->title('New catalog token')
                        ->body($token)
                        ->persistent()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
