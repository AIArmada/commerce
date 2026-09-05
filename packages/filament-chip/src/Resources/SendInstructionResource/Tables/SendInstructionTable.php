<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\SendInstructionResource\Tables;

use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

final class SendInstructionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('bankAccount.name')
                    ->label('Recipient')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('bankAccount.account_number')
                    ->label('Account')
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (?string $state): string => MoneyFormatter::formatMajor($state ?? '0', config('filament-chip.default_currency', 'MYR')))
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),

                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'completed' => 'success',
                        'received', 'enquiring', 'executing', 'reviewing', 'accepted' => 'warning',
                        'rejected', 'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => (string) str($state ?? 'unknown')->headline()),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Status')
                    ->options([
                        'received' => 'Received',
                        'enquiring' => 'Enquiring',
                        'executing' => 'Executing',
                        'reviewing' => 'Under Review',
                        'accepted' => 'Accepted',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'deleted' => 'Deleted',
                    ]),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                Action::make('resend_webhook')
                    ->label('Resend Webhook')
                    ->icon(Heroicon::ArrowPath)
                    ->color('gray')
                    ->action(function (SendInstruction $record): void {
                        $scopedRecord = self::resolveScopedSendInstruction($record);

                        if ($scopedRecord === null) {
                            Notification::make()
                                ->title('Payout is outside your owner scope')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            app(ChipSendService::class)->resendSendInstructionWebhook((int) $scopedRecord->id);

                            Notification::make()
                                ->title('Webhook resent')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Failed to resend webhook')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-chip.polling_interval', '45s'));
    }

    private static function resolveScopedSendInstruction(SendInstruction $record): ?SendInstruction
    {
        return SendInstruction::query()
            ->forOwner()
            ->whereKey($record->getKey())
            ->first();
    }
}
