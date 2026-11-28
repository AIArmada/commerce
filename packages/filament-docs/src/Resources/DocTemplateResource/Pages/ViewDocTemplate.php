<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages;

use AIArmada\Docs\Contracts\DocumentTemplates;
use AIArmada\Docs\Features\Templates\Models\DocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

final class ViewDocTemplate extends ViewRecord
{
    protected static string $resource = DocTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon(Heroicon::OutlinedPencil),

            Actions\Action::make('set_default')
                ->label('Set as Default')
                ->icon(Heroicon::OutlinedStar)
                ->color('warning')
                ->authorize(fn (DocTemplate $record): bool => DocTemplateResource::canEdit($record))
                ->visible(fn (DocTemplate $record): bool => ! $record->is_default)
                ->requiresConfirmation()
                ->action(function (DocTemplate $record): void {
                    abort_unless(DocTemplateResource::canEdit($record), 403);
                    app(DocumentTemplates::class)->setDefault((string) $record->getKey());
                    Notification::make()
                        ->title('Template set as default')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('delete')
                ->authorize(fn (DocTemplate $record): bool => DocTemplateResource::canDelete($record))
                ->label('Delete')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (DocTemplate $record): void {
                    abort_unless(DocTemplateResource::canDelete($record), 403);
                    app(DocumentTemplates::class)->remove((string) $record->getKey());
                }),
        ];
    }
}
