<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks\Resources\LinkResource\Tables;

use AIArmada\Links\Actions\DeactivateLink;
use AIArmada\Links\Actions\ReactivateLink;
use AIArmada\Links\Models\Link;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class LinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->badge()
                    ->copyable()
                    ->copyMessage('Slug copied')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('destination_url')
                    ->label('Destination')
                    ->limit(40)
                    ->url(fn (Link $record): string => $record->destination_url)
                    ->openUrlInNewTab()
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_clicks')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('human_clicks')
                    ->label('Human')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_clicked_at')
                    ->label('Last click')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('deactivated_at')
                    ->label('Active')
                    ->state(fn (Link $record): bool => $record->deactivated_at === null)
                    ->boolean(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Active only')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNull('deactivated_at')
                        ->where(fn (Builder $inner): Builder => $inner
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now()))),

                Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expires_at')->where('expires_at', '<=', now())),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Link $record): string => $record->cloakedUrl())
                    ->openUrlInNewTab(),

                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Link $record): bool => $record->deactivated_at === null)
                    ->action(function (Link $record): void {
                        DeactivateLink::run($record);

                        Notification::make()->title('Link deactivated')->success()->send();
                    }),

                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (Link $record): bool => $record->deactivated_at !== null)
                    ->action(function (Link $record): void {
                        ReactivateLink::run($record);

                        Notification::make()->title('Link reactivated')->success()->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
