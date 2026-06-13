<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\FilamentEvents\Actions\Exporter\EventRegistrationExporter;
use AIArmada\FilamentEvents\Actions\Importer\EventRegistrationImporter;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class EventRegistrationResource extends Resource
{
    protected static ?string $model = EventRegistration::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-events.navigation.group');
    }

    /* @phpstan-ignore return.type */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<EventRegistration> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
            ->with(['event', 'occurrence', 'registrant', 'participants']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registration_no')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('registrant.first_name')
                    ->label('Name')
                    ->state(function (EventRegistration $record): string {
                        $registrant = $record->registrant;
                        if ($registrant instanceof Customer) {
                            return mb_trim(($registrant->first_name ?? '') . ' ' . ($registrant->last_name ?? ''));
                        }

                        return $record->participants->first()?->name ?? '';
                    })
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('registrant', fn (Builder $q) => $q
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"))
                        ->orWhereHas('participants', fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%"))),
                Tables\Columns\TextColumn::make('registrant.email')
                    ->label('Email')
                    ->state(function (EventRegistration $record): ?string {
                        $registrant = $record->registrant;
                        if ($registrant instanceof Customer && $registrant->email) {
                            return $registrant->email;
                        }

                        return $record->participants->first()?->metadata['contact']['email'] ?? null;
                    })
                    ->copyable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('registrant', fn (Builder $q) => $q
                            ->where('email', 'like', "%{$search}%"))),
                Tables\Columns\TextColumn::make('registrant.phone')
                    ->label('Phone')
                    ->state(function (EventRegistration $record): ?string {
                        $registrant = $record->registrant;
                        if ($registrant instanceof Customer && $registrant->phone) {
                            return $registrant->phone;
                        }

                        return $record->participants->first()?->metadata['contact']['phone'] ?? null;
                    })
                    ->copyable(),
                Tables\Columns\TextColumn::make('registrant.company')
                    ->label('Company')
                    ->state(function (EventRegistration $record): ?string {
                        $registrant = $record->registrant;

                        return $registrant instanceof Customer ? $registrant->company : null;
                    }),
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event'),
                Tables\Columns\TextColumn::make('registration_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending', 'waitlisted' => 'warning',
                        'approved', 'completed' => 'success',
                        'cancelled', 'rejected', 'expired' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('source')
                    ->badge(),
                Tables\Columns\TextColumn::make('total_participants')
                    ->numeric(),
                Tables\Columns\TextColumn::make('registered_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                        'waitlisted' => 'Waitlisted',
                        'expired' => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('registration_type'),
                Tables\Filters\SelectFilter::make('source'),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(EventRegistrationImporter::class)
                    ->label('Import Registrations'),
                ExportAction::make()
                    ->exporter(EventRegistrationExporter::class)
                    ->label('Export Registrations'),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Registration Details')
                    ->schema([
                        TextEntry::make('registration_no'),
                        TextEntry::make('registration_type')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('source')->badge(),
                        TextEntry::make('event.title'),
                        TextEntry::make('occurrence.title'),
                        TextEntry::make('registrant_type'),
                        TextEntry::make('registrant_id'),
                        TextEntry::make('total_participants')->numeric(),
                        TextEntry::make('total_amount')->numeric(),
                        TextEntry::make('currency'),
                        TextEntry::make('payment_status')->badge(),
                        TextEntry::make('external_order_id'),
                        TextEntry::make('registered_at')->dateTime(),
                        TextEntry::make('approved_at')->dateTime(),
                        TextEntry::make('cancelled_at')->dateTime(),
                        TextEntry::make('rejected_at')->dateTime(),
                        TextEntry::make('notes'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => EventRegistrationResource\Pages\ListEventRegistrations::route('/'),
            'view' => EventRegistrationResource\Pages\ViewEventRegistration::route('/{record}'),
        ];
    }
}
