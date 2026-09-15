<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use AIArmada\FilamentTicketing\Support\TicketableReferenceGuard;
use AIArmada\FilamentTicketing\Support\TicketMoney;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Ticketing\Enums\TicketAccessType;
use AIArmada\Ticketing\Enums\TicketTypeStatus;
use AIArmada\Ticketing\Enums\TicketTypeVisibility;
use AIArmada\Ticketing\Support\TicketableTypeRegistry;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared ticket-type form, used by the resource and relation managers alike.
 *
 * Pass the owning record when the form manages types for a fixed ticketable
 * (relation-manager context): the ticketable picker is omitted and the code
 * uniqueness rule scopes to the owner instead of the form state.
 */
final class TicketTypeFormSchema
{
    /**
     * @return array<int, Component>
     */
    public static function make(?Model $fixedTicketable = null): array
    {
        return [
            ...($fixedTicketable === null ? [self::ticketableSelect()] : []),
            Section::make('Details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('code')
                        ->required()
                        ->maxLength(50)
                        // Mirrors the database unique on
                        // (ticketable_type, ticketable_id, code).
                        ->unique(ignoreRecord: true, modifyRuleUsing: static function (Unique $rule, Get $get) use ($fixedTicketable): Unique {
                            return $rule
                                ->where('ticketable_type', (string) ($get('ticketable_type') ?: $fixedTicketable?->getMorphClass()))
                                ->where('ticketable_id', (string) ($get('ticketable_id') ?: $fixedTicketable?->getKey()));
                        }),
                    Textarea::make('description')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                    Select::make('access_type')
                        ->options(TicketAccessType::options())
                        ->required(),
                    Select::make('seating_mode')
                        ->options(SeatingMode::options())
                        ->nullable(),
                    TextInput::make('price')
                        ->numeric()
                        ->minValue(0)
                        ->suffix(fn (Get $get): string => (string) ($get('currency') ?? 'MYR'))
                        ->helperText('Ticket price in major units; stored as integer minor units.')
                        ->formatStateUsing(
                            static fn (?int $state): ?string => TicketMoney::toDisplay($state)
                        )
                        ->dehydrateStateUsing(
                            static fn (?string $state): ?int => TicketMoney::toMinor($state)
                        ),
                    TextInput::make('currency')
                        ->alpha()
                        ->maxLength(3)
                        ->default('MYR')
                        ->dehydrateStateUsing(
                            static fn (?string $state): ?string => $state !== null ? mb_strtoupper($state) : null
                        ),
                    TextInput::make('admits_quantity')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(1),
                    TextInput::make('min_quantity')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->nullable()
                        ->rules([static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (TicketTypeResource::minQuantityExceedsMax($value, $get('max_quantity'))) {
                                $fail('The minimum quantity must not exceed the maximum quantity.');
                            }
                        }]),
                    TextInput::make('max_quantity')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->nullable(),
                ])->columns(2),
            Section::make('Sales & Visibility')
                ->schema([
                    Select::make('status')
                        ->options(TicketTypeStatus::options())
                        ->required(),
                    Select::make('visibility')
                        ->options(TicketTypeVisibility::options())
                        ->required(),
                    DateTimePicker::make('sales_starts_at')
                        ->nullable(),
                    DateTimePicker::make('sales_ends_at')
                        ->nullable()
                        ->rules(['after_or_equal:sales_starts_at']),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                ])->columns(2),
        ];
    }

    private static function ticketableSelect(): MorphToSelect
    {
        return MorphToSelect::make('ticketable')
            ->types(
                fn (TicketableTypeRegistry $registry) => collect($registry->all())->map(
                    fn (string $class): MorphToSelect\Type => MorphToSelect\Type::make($class)
                        ->titleAttribute(TicketableReferenceGuard::titleAttributeFor($class))
                        ->searchColumns(TicketableReferenceGuard::searchColumnsFor($class))
                        ->modifyOptionsQueryUsing(
                            static fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false)
                        )
                )->toArray()
            )
            ->searchable()
            ->required();
    }
}
