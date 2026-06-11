<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\RegistrationResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Enums\RegistrationAttendanceSource;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Occurrence;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema(self::formSchema(includeOccurrenceField: true));
    }

    /**
     * @return array<int, mixed>
     */
    public static function formSchema(bool $includeOccurrenceField = true): array
    {
        $registrationFields = [
            TextInput::make('code')
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Leave blank to generate a registration code.'),

            Select::make('status')
                ->options(RegistrationStatus::options())
                ->required()
                ->default(RegistrationStatus::Pending->value),

            Select::make('attendance_source')
                ->label('Attendance Source')
                ->options(RegistrationAttendanceSource::options())
                ->required()
                ->default(RegistrationAttendanceSource::Registration->value),

            TextInput::make('first_name')
                ->required()
                ->maxLength(255),

            TextInput::make('last_name')
                ->maxLength(255)
                ->default(''),

            TextInput::make('email')
                ->email()
                ->required(fn (Get $get): bool => self::emailIsRequiredForAttendanceSource($get('attendance_source')))
                ->maxLength(255),

            TextInput::make('phone')
                ->tel()
                ->maxLength(255),

            TextInput::make('company')
                ->maxLength(255),
        ];

        if ($includeOccurrenceField) {
            array_unshift(
                $registrationFields,
                Select::make('occurrence_id')
                    ->label('Occurrence')
                    ->relationship(
                        name: 'occurrence',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false)->with('event'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Occurrence $record): string => self::occurrenceLabel($record))
                    ->required()
                    ->searchable()
                    ->preload(),
            );
        }

        return [
            Grid::make(3)
                ->schema([
                    Section::make('Registration')
                        ->schema($registrationFields)
                        ->columns(2)
                        ->columnSpan(['lg' => 2]),

                    Section::make('Commerce Links')
                        ->schema([
                            Select::make('order_id')
                                ->label('Order')
                                ->relationship(
                                    name: 'order',
                                    titleAttribute: 'order_number',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('order_item_id')
                                ->label('Order Item')
                                ->relationship(
                                    name: 'orderItem',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('purchaser_customer_id')
                                ->label('Purchaser')
                                ->relationship(
                                    name: 'purchaserCustomer',
                                    titleAttribute: 'email',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->getOptionLabelFromRecordUsing(fn (Customer $record): string => self::customerLabel($record))
                                ->searchable()
                                ->preload(),

                            Select::make('participant_customer_id')
                                ->label('Participant Customer')
                                ->relationship(
                                    name: 'participantCustomer',
                                    titleAttribute: 'email',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->getOptionLabelFromRecordUsing(fn (Customer $record): string => self::customerLabel($record))
                                ->searchable()
                                ->preload(),
                        ])
                        ->columnSpan(['lg' => 1]),

                    Section::make('Lifecycle')
                        ->schema([
                            DateTimePicker::make('checked_in_at'),
                            DateTimePicker::make('cancelled_at'),
                            KeyValue::make('metadata')
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
        ];
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

    public static function customerLabel(Customer $customer): string
    {
        $name = (string) $customer->getAttribute('full_name');
        $email = (string) $customer->getAttribute('email');

        return mb_trim("{$name} ({$email})");
    }

    private static function emailIsRequiredForAttendanceSource(mixed $source): bool
    {
        if ($source instanceof RegistrationAttendanceSource) {
            return $source === RegistrationAttendanceSource::Registration;
        }

        if (is_string($source) && RegistrationAttendanceSource::tryFrom($source) instanceof RegistrationAttendanceSource) {
            return RegistrationAttendanceSource::from($source) === RegistrationAttendanceSource::Registration;
        }

        return true;
    }
}
