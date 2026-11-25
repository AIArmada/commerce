<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\CreatorPostbackResource\RelationManagers;

use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\DeliverCreatorPostback;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

final class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event_type')
            ->columns([
                TextColumn::make('event_type')
                    ->label('Event')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PostbackDeliveryStatus | string $state): string => $state instanceof PostbackDeliveryStatus ? $state->label() : (PostbackDeliveryStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (PostbackDeliveryStatus | string $state): string => $state instanceof PostbackDeliveryStatus ? $state->color() : (PostbackDeliveryStatus::tryFrom((string) $state)?->color() ?? 'gray')),

                TextColumn::make('http_status')
                    ->label('HTTP')
                    ->numeric(),

                TextColumn::make('attempts')
                    ->numeric(),

                TextColumn::make('last_error')
                    ->label('Last error')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('delivered_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('resolved_url')
                    ->label('Resolved URL')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([])
            ->actions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (CreatorPostbackDelivery $record): bool => $record->status !== PostbackDeliveryStatus::Delivered)
                    ->action(function (CreatorPostbackDelivery $record): void {
                        try {
                            $delivery = app(DeliverCreatorPostback::class)->execute((string) $record->getKey());

                            $notification = Notification::make()
                                ->title('Delivery retried')
                                ->body($delivery->status->label());

                            if ($delivery->status === PostbackDeliveryStatus::Delivered) {
                                $notification->success();
                            } else {
                                $notification->danger();
                            }

                            $notification->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Delivery failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
