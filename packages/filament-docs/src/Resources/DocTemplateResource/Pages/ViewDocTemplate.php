<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages;

use AIArmada\Docs\Models\DocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
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
                ->visible(fn (DocTemplate $record): bool => ! $record->is_default)
                ->requiresConfirmation()
                ->action(function (DocTemplate $record): void {
                    DocsOwnerScope::assertCanMutateRecord($record, 'Template not found.');
                    $record->setAsDefault();
                    Notification::make()
                        ->title('Template set as default')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('delete')
                ->label('Delete')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (DocTemplate $record): void {
                    DocsOwnerScope::assertCanMutateRecord($record, 'Template not found.');
                    $record->delete();
                }),
        ];
    }
}
