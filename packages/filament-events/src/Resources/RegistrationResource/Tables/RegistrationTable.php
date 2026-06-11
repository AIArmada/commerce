<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\RegistrationResource\Tables;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Enums\RegistrationAttendanceSource;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\FilamentEvents\Resources\RegistrationResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class RegistrationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('full_name')
                    ->label('Participant')
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn (Registration $record): string => self::registrationContactLabel($record)),

                TextColumn::make('attendance_source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (RegistrationAttendanceSource $state): string => $state->label())
                    ->color(fn (RegistrationAttendanceSource $state): string => $state === RegistrationAttendanceSource::WalkIn ? 'info' : 'primary')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state->color()),

                TextColumn::make('occurrence.event.name')
                    ->label('Event')
                    ->sortable(),

                TextColumn::make('occurrence.starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('company')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('checked_in_at')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Not checked in')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(RegistrationStatus::options()),

                SelectFilter::make('occurrence_id')
                    ->label('Occurrence')
                    ->options(static fn (): array => OwnerUiScope::apply(Occurrence::query(), includeGlobal: false)
                        ->with('event')
                        ->orderByDesc('starts_at')
                        ->get()
                        ->mapWithKeys(fn (Occurrence $occurrence): array => [$occurrence->id => self::occurrenceLabel($occurrence)])
                        ->all()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                RegistrationResource::approveAction(),
                RegistrationResource::rejectAction(),
                RegistrationResource::checkInAction(),
                RegistrationResource::cancelAction(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function occurrenceLabel(Occurrence $occurrence): string
    {
        $event = $occurrence->event;
        $eventName = $event instanceof Model
            ? (string) ($event->getAttribute('name') ?? $event->getAttribute('title') ?? 'Event')
            : 'Event';
        $startsAt = $occurrence->starts_at?->format('d M Y H:i') ?? 'unscheduled';

        return "{$eventName} ({$startsAt})";
    }

    public static function registrationContactLabel(Registration $registration): string
    {
        if (is_string($registration->email) && mb_trim($registration->email) !== '') {
            return $registration->email;
        }

        return $registration->attendance_source instanceof RegistrationAttendanceSource
            ? $registration->attendance_source->label()
            : RegistrationAttendanceSource::Registration->label();
    }
}
