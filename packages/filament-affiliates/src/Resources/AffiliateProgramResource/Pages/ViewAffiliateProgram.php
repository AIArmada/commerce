<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\Pages;

use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Services\ProgramCatalogService;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
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
                    'snapshot' => app(ProgramCatalogService::class)->snapshot($record),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }
}
