<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationParticipant;
use AIArmada\Events\States\RegistrationStatus\RegistrationStatus;
use AIArmada\Events\Support\ModelResolver;
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
use Filament\Support\Contracts\HasColor;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class EventRegistrationResource extends Resource
{
    protected static ?string $model = null;

    public static function getModel(): string
    {
        return ModelResolver::registrationClass();
    }

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-events.resources.navigation_sort.registration');

        return is_numeric($sort) ? (int) $sort : null;
    }

    /* @phpstan-ignore return.type */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<EventRegistration> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
            ->with(['event', 'occurrence', 'registrant', 'participants.contactMethods']);
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

                        return static::participantContactValue($record->participants->first(), 'email');
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

                        return static::participantContactValue($record->participants->first(), 'phone');
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
                    ->color(fn (mixed $state): string | array | null => $state instanceof HasColor ? $state->getColor() : 'gray'),
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
                    ->options(RegistrationStatus::options()),
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

    private static function participantContactValue(?EventRegistrationParticipant $participant, string $type): ?string
    {
        if (! $participant instanceof EventRegistrationParticipant) {
            return null;
        }

        $contact = $participant->relationLoaded('contactMethods')
            ? $participant->contactMethods
                ->where('type', $type)
                ->sortByDesc('is_primary')
                ->sortBy('sort_order')
                ->first()
            : $participant->contactMethods()
                ->where('type', $type)
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->first();

        $value = $contact?->normalized_value ?? $contact?->value;

        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value !== '' ? $value : null;
    }
}
