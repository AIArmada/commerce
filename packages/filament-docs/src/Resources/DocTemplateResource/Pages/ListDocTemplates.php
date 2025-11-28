<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages;

use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

final class ListDocTemplates extends ListRecords
{
    protected static string $resource = DocTemplateResource::class;

    public function getTitle(): string
    {
        return 'Document Templates';
    }

    public function getSubheading(): string
    {
        return 'Manage document templates for invoices, receipts, and more';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
