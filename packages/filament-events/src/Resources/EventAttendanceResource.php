<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\FilamentEvents\Actions\Exporter\EventAttendanceExporter;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class EventAttendanceResource extends Resource
{
    protected static ?string $model = EventAttendance::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    /* @phpstan-ignore return.type */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<EventAttendance> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
            ->with(['event', 'occurrence', 'session', 'registration']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event'),
                Tables\Columns\TextColumn::make('occurrence.title')
                    ->label('Occurrence'),
                Tables\Columns\TextColumn::make('attendance_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('checked_in_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_in_source')
                    ->badge(),
                Tables\Columns\TextColumn::make('attendee_type'),
                Tables\Columns\TextColumn::make('attendee_id'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('attendance_type'),
                Tables\Filters\SelectFilter::make('check_in_source'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(EventAttendanceExporter::class)
                    ->label('Export Attendance'),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Attendance Details')
                    ->schema([
                        TextEntry::make('event.title'),
                        TextEntry::make('occurrence.title'),
                        TextEntry::make('session.title'),
                        TextEntry::make('registration.registration_no'),
                        TextEntry::make('attendance_type')->badge(),
                        TextEntry::make('checked_in_at')->dateTime(),
                        TextEntry::make('checked_out_at')->dateTime(),
                        TextEntry::make('check_in_source')->badge(),
                        TextEntry::make('attendee_type'),
                        TextEntry::make('attendee_id'),
                        TextEntry::make('cancelled_at')->dateTime(),
                        TextEntry::make('notes'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => EventAttendanceResource\Pages\ListEventAttendances::route('/'),
            'view' => EventAttendanceResource\Pages\ViewEventAttendance::route('/{record}'),
        ];
    }
}
