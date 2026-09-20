<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Actions\SyncSiteCatalog;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Schemas\AffiliateSiteForm;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateSite extends EditRecord
{
    protected static string $resource = AffiliateSiteResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['domain']) && is_string($data['domain'])) {
            $data['domain'] = mb_strtolower(mb_trim($data['domain']));
        }

        // Keep the verified invariant (status=verified ⟺ verified_at set):
        // stamp on entry into Verified, clear on exit from Verified.
        $originalStatus = $this->record instanceof AffiliateSite ? $this->record->status : null;
        $newStatus = $data['status'] ?? $originalStatus;

        if ($newStatus === AffiliateSite::STATUS_VERIFIED && $originalStatus !== AffiliateSite::STATUS_VERIFIED) {
            $data['verified_at'] ??= CarbonImmutable::now();
        } elseif ($newStatus !== AffiliateSite::STATUS_VERIFIED && $originalStatus === AffiliateSite::STATUS_VERIFIED) {
            $data['verified_at'] = null;
        }

        return AffiliateSiteForm::mergeCatalogToken($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_catalog')
                ->label('Sync catalog')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->action(fn (AffiliateSite $record): array => app(SyncSiteCatalog::class)->handle($record)),
            Actions\DeleteAction::make(),
        ];
    }
}
