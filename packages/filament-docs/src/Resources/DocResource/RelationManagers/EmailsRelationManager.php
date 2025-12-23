<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\RelationManagers;

use AIArmada\Docs\Models\DocEmail;
use AIArmada\Docs\Services\DocEmailService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EmailsRelationManager extends RelationManager
{
    protected static string $relationship = 'emails';

    protected static ?string $recordTitleAttribute = 'recipient_email';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('recipient_email')
            ->columns([
                TextColumn::make('recipient_email')
                    ->label('Recipient')
                    ->searchable(),

                TextColumn::make('subject')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'queued' => 'warning',
                        'failed' => 'danger',
                        'bounced' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('open_count')
                    ->label('Opens')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('click_count')
                    ->label('Clicks')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),

                TextColumn::make('opened_at')
                    ->label('First Open')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'queued' => 'Queued',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'bounced' => 'Bounced',
                    ]),
            ])
            ->recordActions([
                Action::make('resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (DocEmail $record): void {
                        $doc = $record->doc;

                        if ($doc === null) {
                            throw new NotFoundHttpException('Document not found.');
                        }

                        app(DocEmailService::class)->send(
                            doc: $doc,
                            recipientEmail: $record->recipient_email,
                            recipientName: $record->recipient_name,
                        );

                        Notification::make()
                            ->title('Email queued')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
            ])
            ->defaultSort('sent_at', 'desc');
    }
}
