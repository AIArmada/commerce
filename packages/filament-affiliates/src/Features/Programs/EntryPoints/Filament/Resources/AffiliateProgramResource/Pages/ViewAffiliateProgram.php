<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\Pages;

use AIArmada\Affiliates\Features\Programs\Api\MerchantCatalog;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateProgram extends ViewRecord
{
    protected static string $resource = AffiliateProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('preview_catalog')
                ->label('Preview network catalog')
                ->icon('heroicon-o-eye')
                ->modalHeading(fn (AffiliateProgram $record): string => 'Network catalog: ' . $record->name)
                ->modalContent(fn (AffiliateProgram $record) => view('filament-affiliates::catalog-preview', [
                    'snapshot' => app(MerchantCatalog::class)->previewById((string) $record->getKey())->payload,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }
}
