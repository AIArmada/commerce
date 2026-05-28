<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\FilamentEvents\Resources\RegistrationResource\Pages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('filament-events.navigation.group', 'Events');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-events.navigation.resources.registrations', 5);
    }

    /**
     * @return Builder<Registration>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Registration> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->with(['occurrence.event', 'order', 'orderItem', 'purchaserCustomer', 'participantCustomer']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', RegistrationStatus::Confirmed)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema(static::formSchema());
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
                ->options(static::statusOptions())
                ->required()
                ->default(RegistrationStatus::Pending->value),

            TextInput::make('first_name')
                ->required()
                ->maxLength(255),

            TextInput::make('last_name')
                ->maxLength(255)
                ->default(''),

            TextInput::make('email')
                ->email()
                ->required()
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
                    ->getOptionLabelFromRecordUsing(fn (Occurrence $record): string => static::occurrenceLabel($record))
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
                                ->getOptionLabelFromRecordUsing(fn (Customer $record): string => static::customerLabel($record))
                                ->searchable()
                                ->preload(),

                            Select::make('participant_customer_id')
                                ->label('Participant Customer')
                                ->relationship(
                                    name: 'participantCustomer',
                                    titleAttribute: 'email',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->getOptionLabelFromRecordUsing(fn (Customer $record): string => static::customerLabel($record))
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Participant')
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn (Registration $record): string => $record->email),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('occurrence.event.name')
                    ->label('Event')
                    ->sortable(),

                Tables\Columns\TextColumn::make('occurrence.starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('company')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Not checked in')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(static::statusOptions()),

                Tables\Filters\SelectFilter::make('occurrence_id')
                    ->label('Occurrence')
                    ->options(static fn (): array => OwnerUiScope::apply(Occurrence::query(), includeGlobal: false)
                        ->with('event')
                        ->orderByDesc('starts_at')
                        ->get()
                        ->mapWithKeys(fn (Occurrence $occurrence): array => [$occurrence->id => static::occurrenceLabel($occurrence)])
                        ->all()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                static::checkInAction(),
                static::cancelAction(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Registration')
                    ->schema([
                        TextEntry::make('code')
                            ->copyable(),
                        TextEntry::make('full_name')
                            ->label('Participant'),
                        TextEntry::make('email')
                            ->copyable(),
                        TextEntry::make('phone')
                            ->copyable()
                            ->placeholder('Not set'),
                        TextEntry::make('company')
                            ->placeholder('Not set'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                            ->color(fn (RegistrationStatus $state): string => $state->color()),
                        TextEntry::make('occurrence.event.name')
                            ->label('Event'),
                        TextEntry::make('occurrence.starts_at')
                            ->label('Starts')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('checked_in_at')
                            ->dateTime()
                            ->placeholder('Not checked in'),
                        TextEntry::make('cancelled_at')
                            ->dateTime()
                            ->placeholder('Not cancelled'),
                    ])
                    ->columns(3),

                Section::make('Commerce Links')
                    ->schema([
                        TextEntry::make('order.order_number')
                            ->label('Order')
                            ->copyable()
                            ->placeholder('Not linked'),
                        TextEntry::make('orderItem.name')
                            ->label('Order Item')
                            ->placeholder('Not linked'),
                        TextEntry::make('purchaserCustomer.full_name')
                            ->label('Purchaser')
                            ->placeholder('Not linked'),
                        TextEntry::make('participantCustomer.full_name')
                            ->label('Participant Customer')
                            ->placeholder('Not linked'),
                    ])
                    ->columns(4),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'create' => Pages\CreateRegistration::route('/create'),
            'view' => Pages\ViewRegistration::route('/{record}'),
            'edit' => Pages\EditRegistration::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'first_name', 'last_name', 'email', 'phone', 'company'];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(RegistrationStatus::cases())
            ->mapWithKeys(fn (RegistrationStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    public static function checkInAction(): Action
    {
        return Action::make('check_in')
            ->label('Check In')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Registration $record): bool => $record->status->canCheckIn())
            ->action(fn (Registration $record): Registration => app(RegistrationService::class)->checkIn($record, [
                'source' => 'filament',
            ]));
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->schema([
                Textarea::make('reason')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->visible(fn (Registration $record): bool => $record->status !== RegistrationStatus::Cancelled)
            ->action(fn (Registration $record, array $data): Registration => app(RegistrationService::class)->cancel(
                $record,
                is_string($data['reason'] ?? null) ? $data['reason'] : null,
            ));
    }

    public static function occurrenceLabel(Occurrence $occurrence): string
    {
        $eventName = $occurrence->event?->name ?? 'Event';
        $startsAt = $occurrence->starts_at?->format('d M Y H:i') ?? 'unscheduled';

        return "{$eventName} ({$startsAt})";
    }

    private static function customerLabel(Customer $customer): string
    {
        $name = (string) $customer->getAttribute('full_name');
        $email = (string) $customer->getAttribute('email');

        return mb_trim("{$name} ({$email})");
    }
}
