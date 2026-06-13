<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\EventRegistrationParticipant;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class EventRegistrationParticipantResource extends Resource
{
    protected static ?string $model = EventRegistrationParticipant::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 11;

    public static function getNavigationLabel(): string
    {
        return 'Participants';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-events.navigation.group');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    /* @phpstan-ignore return.type */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query
            ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
            ->with(['event', 'registration']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->state(fn (EventRegistrationParticipant $record): ?string => $record->metadata['contact']['email'] ?? null)
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->state(fn (EventRegistrationParticipant $record): ?string => $record->metadata['contact']['phone'] ?? null)
                    ->copyable(),
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event'),
                Tables\Columns\TextColumn::make('registration.registration_no')
                    ->label('Registration #'),
                Tables\Columns\TextColumn::make('is_primary')
                    ->label('Primary')
                    ->badge()
                    ->state(fn (EventRegistrationParticipant $record): string => $record->is_primary ? 'Yes' : 'No')
                    ->color(fn (string $state): string => $state === 'Yes' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'cancelled' => 'Cancelled',
                        'transferred' => 'Transferred',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\SelectFilter::make('is_primary')
                    ->label('Primary Registrant')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Participant Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')
                            ->state(fn (EventRegistrationParticipant $record): ?string => $record->metadata['contact']['email'] ?? null),
                        TextEntry::make('phone')
                            ->state(fn (EventRegistrationParticipant $record): ?string => $record->metadata['contact']['phone'] ?? null),
                        TextEntry::make('event.title'),
                        TextEntry::make('registration.registration_no'),
                        TextEntry::make('is_primary')
                            ->state(fn (EventRegistrationParticipant $record): string => $record->is_primary ? 'Yes' : 'No'),
                        TextEntry::make('relationship_to_registrant'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('age'),
                        TextEntry::make('gender'),
                        TextEntry::make('notes'),
                        TextEntry::make('created_at')->dateTime(),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => EventRegistrationParticipantResource\Pages\ListEventRegistrationParticipants::route('/'),
            'view' => EventRegistrationParticipantResource\Pages\ViewEventRegistrationParticipant::route('/{record}'),
        ];
    }
}
