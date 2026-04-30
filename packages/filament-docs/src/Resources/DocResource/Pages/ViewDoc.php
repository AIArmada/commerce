<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Pages;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

final class ViewDoc extends ViewRecord
{
    protected static string $resource = DocResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon(Heroicon::OutlinedPencil),

            Actions\Action::make('generate_pdf')
                ->label('Generate PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Generate PDF')
                ->modalDescription('This will generate a new PDF for this document. Any existing PDF will be overwritten.')
                ->action(function (Doc $record): void {
                    DocsOwnerScope::assertCanMutateDoc($record);

                    $docService = app(DocService::class);
                    $docService->generatePdf($record, save: true);

                    Notification::make()
                        ->title('PDF Generated')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->visible(fn (Doc $record): bool => $record->pdf_path !== null)
                ->url(fn (Doc $record): string => route('filament-docs.download', ['doc' => $record->getKey()]))
                ->openUrlInNewTab(),

            Actions\ActionGroup::make([
                Actions\Action::make('mark_sent')
                    ->label('Mark as Sent')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('info')
                    ->visible(fn (Doc $record): bool => in_array($record->status, [DocStatus::DRAFT, DocStatus::PENDING]))
                    ->requiresConfirmation()
                    ->action(function (Doc $record): void {
                        DocsOwnerScope::assertCanMutateDoc($record);
                        $record->markAsSent();
                        Notification::make()->title('Document marked as sent')->success()->send();
                    }),

                Actions\Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (Doc $record): bool => $record->canBePaid())
                    ->requiresConfirmation()
                    ->action(function (Doc $record): void {
                        DocsOwnerScope::assertCanMutateDoc($record);
                        $record->markAsPaid();
                        Notification::make()->title('Document marked as paid')->success()->send();
                    }),

                Actions\Action::make('cancel')
                    ->label('Cancel Document')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Doc $record): bool => $record->status !== DocStatus::PAID && $record->status !== DocStatus::CANCELLED)
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Document')
                    ->modalDescription('Are you sure you want to cancel this document? This action cannot be undone.')
                    ->action(function (Doc $record): void {
                        DocsOwnerScope::assertCanMutateDoc($record);
                        $record->cancel();
                        Notification::make()->title('Document cancelled')->warning()->send();
                    }),
            ])
                ->label('Status Actions')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray'),

            Actions\Action::make('delete')
                ->label('Delete')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (Doc $record): void {
                    DocsOwnerScope::assertCanMutateDoc($record);
                    $record->delete();
                }),
        ];
    }
}
