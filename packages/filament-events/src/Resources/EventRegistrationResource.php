<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\RegistrationStatus;
use AIArmada\Events\Platform\ModelResolver;
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

    public static function getEloquentQuery(): Builder
    {
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
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $pattern = LikeSearch::contains($search);

                        return $query->where(function (Builder $nested) use ($pattern): void {
                            $nested->whereHas('participants', fn (Builder $q) => LikeSearch::whereLike($q, 'name', $pattern));

                            if (class_exists(Customer::class)) {
                                $nested->orWhereHasMorph('registrant', [Customer::class], function (Builder $q) use ($pattern): void {
                                    LikeSearch::whereLike($q, 'first_name', $pattern);
                                    LikeSearch::orWhereLike($q, 'last_name', $pattern);
                                });
                            }
                        });
                    }),
                Tables\Columns\TextColumn::make('registrant.email')
                    ->label('Email')
                    ->state(function (EventRegistration $record): ?string {
                        $registrant = $record->registrant;
                        if ($registrant instanceof Customer) {
                            return $registrant->resolveEmail()
                                ?? $record->participants->first()?->resolveEmail();
                        }

                        return $record->participants->first()?->resolveEmail();
                    })
                    ->copyable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $pattern = LikeSearch::contains($search);

                        $matchContact = static fn (Builder $contactQuery): Builder => $contactQuery
                            ->where('type', 'email')
                            ->whereNotNull('normalized_value')
                            ->where(fn (Builder $validity): Builder => $validity->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
                            ->where(fn (Builder $validity): Builder => $validity->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
                            ->where(function (Builder $valueQuery) use ($pattern): void {
                                LikeSearch::whereLike($valueQuery, 'normalized_value', $pattern);
                                LikeSearch::orWhereLike($valueQuery, 'value', $pattern);
                            });

                        return $query->where(function (Builder $nested) use ($matchContact): void {
                            $nested->whereHas('participants.contactMethods', $matchContact);

                            if (class_exists(Customer::class)) {
                                $nested->orWhereHasMorph(
                                    'registrant',
                                    [Customer::class],
                                    fn (Builder $registrantQuery): Builder => $registrantQuery->whereHas('contactMethods', $matchContact),
                                );
                            }
                        });
                    }),
                Tables\Columns\TextColumn::make('registrant.phone')
                    ->label('Phone')
                    ->state(function (EventRegistration $record): ?string {
                        $registrant = $record->registrant;
                        if ($registrant instanceof Customer) {
                            return $registrant->resolvePhone()
                                ?? $record->participants->first()?->resolvePhone();
                        }

                        return $record->participants->first()?->resolvePhone();
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
                    ->label('Import Registrations')
                    ->visible(fn (): bool => static::canCreate())
                    ->authorize(fn (): bool => static::canCreate()),
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
