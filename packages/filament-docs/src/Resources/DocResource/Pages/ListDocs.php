<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Pages;

use AIArmada\FilamentDocs\Resources\DocResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

final class ListDocs extends ListRecords
{
    protected static string $resource = DocResource::class;

    public function getTitle(): string
    {
        return 'Documents';
    }

    public function getSubheading(): string
    {
        return 'Manage invoices, receipts, and other documents';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
