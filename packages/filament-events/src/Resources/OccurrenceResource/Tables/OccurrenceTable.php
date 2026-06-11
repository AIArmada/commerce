<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\OccurrenceResource\Tables;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSubLocation;
use AIArmada\Events\Support\Integration\EventAddressRegistry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class OccurrenceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->placeholder('Unnamed run')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OccurrenceStatus $state): string => $state->label())
                    ->color(fn (OccurrenceStatus $state): string => $state->color()),

                TextColumn::make('participation_mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn (OccurrenceParticipationMode $state): string => $state->label())
                    ->color(fn (OccurrenceParticipationMode $state): string => $state->color())
                    ->toggleable(),

                TextColumn::make('approval_required')
                    ->label('Approval')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Required' : 'Open')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('waitlist_enabled')
                    ->label('Waitlist')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled')
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('location_label')
                    ->label('Location')
                    ->placeholder('No address')
                    ->toggleable(),

                TextColumn::make('registrations_count')
                    ->label('Registrations')
                    ->counts('registrations')
                    ->sortable(),

                TextColumn::make('timezone')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(OccurrenceStatus::options()),

                SelectFilter::make('participation_mode')
                    ->label('Participation Mode')
                    ->options(OccurrenceParticipationMode::options()),

                SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(static fn (): array => OwnerUiScope::apply(Event::query(), includeGlobal: false)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),

                SelectFilter::make('address_type')
                    ->label('Address Type')
                    ->options(self::addressTypeOptions()),

                SelectFilter::make('sub_location_id')
                    ->label('Sub-location')
                    ->options(self::subLocationOptions()),

                Filter::make('starts_at')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('starts_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('starts_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function addressTypeOptions(): array
    {
        return EventAddressRegistry::labels();
    }

    /**
     * @return array<string, string>
     */
    private static function subLocationOptions(): array
    {
        return OwnerUiScope::apply(EventSubLocation::query(), includeGlobal: true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
