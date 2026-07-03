<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Products\Models\Product;
use AIArmada\Ticketing\Models\TicketType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CommissionRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionRules';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Select::make('rule_type')
                ->options(CommissionRuleType::class)
                ->required()
                ->live()
                ->afterStateUpdated(function (CommissionRuleType $state, Set $set): void {
                    $set('priority', (string) $state->priority());
                }),

            Select::make('purchasable_id')
                ->label('Purchasable Item')
                ->hint('Only applies when Rule Type is Product')
                ->searchable()
                ->placeholder('Search for a product or ticket type...')
                ->dehydrated(false)
                ->helperText('Select the specific product or event ticket this rule applies to.')
                ->afterStateHydrated(function (?string $state, Get $get, Set $set, $record): void {
                    if ($record === null) {
                        return;
                    }
                    $conditions = $record->conditions ?? [];
                    $type = $conditions['purchasable_type'] ?? null;
                    $id = $conditions['purchasable_id'] ?? null;
                    if ($type === null || $id === null) {
                        return;
                    }
                    $prefix = $this->purchasablePrefix($type);
                    if ($prefix !== null) {
                        $set('purchasable_id', "{$prefix}:{$id}");
                    }
                })
                ->options(function (): array {
                    $options = [];

                    if (class_exists(Product::class)) {
                        $products = Product::query()
                            ->where('status', 'active')
                            ->get(['id', 'name', 'sku']);

                        foreach ($products as $product) {
                            $label = $product->name;
                            if (filled($product->sku)) {
                                $label .= " ({$product->sku})";
                            }
                            $options["product:{$product->id}"] = '[Product] ' . $label;
                        }
                    }

                    if (class_exists(TicketType::class)) {
                        $ticketTypes = TicketType::query()
                            ->where('status', 'active')
                            ->whereHasMorph('ticketable', [
                                Event::class,
                                EventOccurrence::class,
                                EventSession::class,
                            ])
                            ->with('ticketable')
                            ->get(['id', 'name', 'code', 'ticketable_id', 'ticketable_type']);

                        foreach ($ticketTypes as $ticket) {
                            $eventName = EventTicketScope::event($ticket)?->title ?? 'Unknown Event';
                            $code = filled($ticket->code) ? " ({$ticket->code})" : '';
                            $options["ticket:{$ticket->id}"] = "[{$eventName}] {$ticket->name}{$code}";
                        }
                    }

                    return $options;
                })
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($state === null) {
                        $set('conditions', null);

                        return;
                    }

                    [$type, $id] = explode(':', $state, 2);

                    $purchasableType = $this->purchasableClass($type);

                    if ($purchasableType === null) {
                        $set('conditions', null);

                        return;
                    }

                    $set('conditions', [
                        'purchasable_type' => $purchasableType,
                        'purchasable_id' => $id,
                    ]);
                }),

            TextInput::make('priority')
                ->numeric()
                ->required()
                ->default(0)
                ->helperText('Higher priority rules are evaluated first. Product rules default to 90.'),

            Select::make('commission_type')
                ->options(CommissionType::class)
                ->required(),

            TextInput::make('commission_value')
                ->label('Commission Value')
                ->numeric()
                ->required()
                ->helperText('Percentage (e.g. 10 = 10%) or fixed amount in cents (e.g. 1000 = RM10).'),

            DateTimePicker::make('starts_at'),

            DateTimePicker::make('ends_at'),

            Toggle::make('is_active')
                ->default(true),

            KeyValue::make('conditions')
                ->keyLabel('Condition')
                ->valueLabel('Value')
                ->columnSpanFull()
                ->helperText('Optional extra matching conditions. Example: {"purchasable_type": "AIArmada\\Ticketing\\Models\\TicketType", "purchasable_id": "uuid-here"} — auto-filled when you select a purchasable item above.')
                ->addActionLabel('Add Condition'),

            KeyValue::make('metadata')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->columnSpanFull()
                ->helperText('Optional metadata for internal reference, e.g. {"notes": "Summer campaign", "approved_by": "Admin"}')
                ->addActionLabel('Add Metadata'),
        ]);
    }

    private function purchasablePrefix(string $fqcn): ?string
    {
        return match ($fqcn) {
            Product::class => 'product',
            TicketType::class => 'ticket',
            default => null,
        };
    }

    private function purchasableClass(string $prefix): ?string
    {
        return match ($prefix) {
            'product' => Product::class,
            'ticket' => TicketType::class,
            default => null,
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('rule_type')
                    ->badge(),

                TextColumn::make('priority')
                    ->sortable(),

                TextColumn::make('commission_type')
                    ->badge(),

                TextColumn::make('commission_value')
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
