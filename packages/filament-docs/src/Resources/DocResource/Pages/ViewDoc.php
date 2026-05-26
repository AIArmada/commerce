<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Pages;

use AIArmada\Docs\DataObjects\ShareLinkData;
use AIArmada\Docs\Enums\ShareLinkAction;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\States\Cancelled;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Pending;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
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

            Actions\Action::make('preview_online')
                ->label('Preview Online')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (Doc $record): string => route('filament-docs.documents.view', ['doc' => $record->getKey()]))
                ->openUrlInNewTab(),

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

            Actions\Action::make('create_share_link')
                ->label('Share')
                ->icon(Heroicon::OutlinedLink)
                ->form([
                    CheckboxList::make('allowed_actions')
                        ->label('Allowed Actions')
                        ->options([
                            ShareLinkAction::View->value => 'View online',
                            ShareLinkAction::Pdf->value => 'Download PDF',
                        ])
                        ->default([ShareLinkAction::View->value])
                        ->required(),

                    DateTimePicker::make('expires_at')
                        ->label('Expires At')
                        ->default(CarbonImmutable::now()->addDays((int) config('docs.sharing.default_expiry_days', 30))),
                ])
                ->action(function (Doc $record, array $data): void {
                    DocsOwnerScope::assertCanMutateDoc($record);

                    $shareLink = app(DocRenderService::class)->createShareLink($record, new ShareLinkData(
                        allowedActions: $data['allowed_actions'] ?? [ShareLinkAction::View->value],
                        expiresAt: filled($data['expires_at'] ?? null) ? CarbonImmutable::parse($data['expires_at']) : null,
                    ));

                    $url = route(self::shareLinkRouteName($shareLink->allowed_actions), ['token' => $shareLink->plainToken]);

                    Notification::make()
                        ->title('Share link created')
                        ->body($url)
                        ->success()
                        ->send();
                }),

            Actions\Action::make('revoke_share_links')
                ->label('Revoke Links')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (Doc $record): void {
                    DocsOwnerScope::assertCanMutateDoc($record);

                    $record->shareLinks()
                        ->whereNull('revoked_at')
                        ->update(['revoked_at' => CarbonImmutable::now()]);

                    Notification::make()
                        ->title('Public links revoked')
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->url(fn (Doc $record): string => route('filament-docs.download', ['doc' => $record->getKey()]))
                ->openUrlInNewTab(),

            Actions\ActionGroup::make([
                Actions\Action::make('mark_sent')
                    ->label('Mark as Sent')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('info')
                    ->visible(fn (Doc $record): bool => self::canMarkAsSent($record))
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
                    ->visible(fn (Doc $record): bool => self::canCancel($record))
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

    private static function canMarkAsSent(Doc $record): bool
    {
        return $record->status->equals(Draft::class) || $record->status->equals(Pending::class);
    }

    private static function canCancel(Doc $record): bool
    {
        return ! $record->status->equals(Paid::class) && ! $record->status->equals(Cancelled::class);
    }

    /**
     * @param  array<int, string>  $allowedActions
     */
    private static function shareLinkRouteName(array $allowedActions): string
    {
        if (in_array(ShareLinkAction::View->value, $allowedActions, true)) {
            return 'docs.share.show';
        }

        if (in_array(ShareLinkAction::Pdf->value, $allowedActions, true)) {
            return 'docs.share.pdf';
        }

        return 'docs.share.show';
    }
}
