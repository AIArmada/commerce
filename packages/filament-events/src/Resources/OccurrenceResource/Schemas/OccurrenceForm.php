<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\OccurrenceResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Support\Integration\EventAddressRegistry;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class OccurrenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema(self::formSchema(includeEventField: true));
    }

    /**
     * @return array<int, mixed>
     */
    public static function formSchema(bool $includeEventField = true): array
    {
        $identityFields = [
            TextInput::make('name')
                ->maxLength(255),

            Select::make('status')
                ->options(self::statusOptions())
                ->required()
                ->default(OccurrenceStatus::Draft->value),

            Select::make('participation_mode')
                ->label('Participation Mode')
                ->options(self::participationModeOptions())
                ->required()
                ->default(OccurrenceParticipationMode::RegistrationRequired->value),

            DateTimePicker::make('starts_at')
                ->required(),

            DateTimePicker::make('ends_at')
                ->after('starts_at'),

            TextInput::make('timezone')
                ->required()
                ->maxLength(64)
                ->default('Asia/Kuala_Lumpur'),
        ];

        if ($includeEventField) {
            array_unshift(
                $identityFields,
                Select::make('event_id')
                    ->label('Event')
                    ->relationship(
                        name: 'event',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                    )
                    ->required()
                    ->searchable()
                    ->preload(),
            );
        }

        return [
            Grid::make(3)
                ->schema([
                    Section::make('Occurrence')
                        ->schema($identityFields)
                        ->columns(2)
                        ->columnSpan(['lg' => 2]),

                    Section::make('Location and Commerce')
                        ->schema([
                            Select::make('address_type')
                                ->label('Address Type')
                                ->options(self::addressTypeOptions())
                                ->placeholder('No address')
                                ->live()
                                ->afterStateUpdated(static function (Set $set): void {
                                    $set('address_id', null);
                                }),

                            Select::make('address_id')
                                ->label('Address')
                                ->required(static fn (Get $get): bool => is_string($get('address_type')))
                                ->searchable()
                                ->placeholder('Select address')
                                ->getSearchResultsUsing(static function (string $search, Get $get): array {
                                    $addressType = $get('address_type');

                                    if (! is_string($addressType) || $addressType === '') {
                                        return [];
                                    }

                                    return EventAddressRegistry::searchResults($addressType, $search);
                                })
                                ->getOptionLabelUsing(static function (mixed $value, Get $get): ?string {
                                    $addressType = $get('address_type');

                                    if (! is_string($addressType) || $addressType === '' || $value === null) {
                                        return null;
                                    }

                                    return EventAddressRegistry::optionLabel($addressType, $value);
                                })
                                ->hidden(static fn (Get $get): bool => ! is_string($get('address_type')))
                                ->dehydrated(static fn (Get $get): bool => is_string($get('address_type'))),

                            Select::make('sub_location_id')
                                ->label('Sub-location')
                                ->placeholder('No sub-location')
                                ->relationship(
                                    name: 'subLocation',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: true),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('product_id')
                                ->label('Product')
                                ->relationship(
                                    name: 'product',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('variant_id')
                                ->label('Variant')
                                ->relationship(
                                    name: 'variant',
                                    titleAttribute: 'sku',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->searchable()
                                ->preload(),

                            KeyValue::make('metadata'),
                        ])
                        ->columnSpan(['lg' => 1]),

                    Section::make('Registration Policy')
                        ->schema([
                            Toggle::make('waitlist_enabled')
                                ->label('Enable Waitlist'),

                            Toggle::make('approval_required')
                                ->label('Approval Required'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),

                    Section::make('Registration Window')
                        ->schema([
                            DateTimePicker::make('registration_opens_at'),
                            DateTimePicker::make('registration_closes_at')
                                ->after('registration_opens_at'),
                            DateTimePicker::make('check_in_opens_at'),
                            DateTimePicker::make('check_in_closes_at')
                                ->after('check_in_opens_at'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(OccurrenceStatus::cases())
            ->mapWithKeys(fn (OccurrenceStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function participationModeOptions(): array
    {
        return collect(OccurrenceParticipationMode::cases())
            ->mapWithKeys(fn (OccurrenceParticipationMode $mode): array => [$mode->value => $mode->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function addressTypeOptions(): array
    {
        return EventAddressRegistry::labels();
    }
}
