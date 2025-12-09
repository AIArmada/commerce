<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockTransactionResource\Schemas;

use AIArmada\FilamentStock\Support\StockableTypeRegistry;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;

final class StockTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        $registry = app(StockableTypeRegistry::class);

        $stockableFields = $registry->hasDefinitions()
            ? self::buildDynamicStockableFields($registry)
            : self::buildManualStockableFields();

        return $schema->schema([
            Section::make('Transaction Details')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('type')
                                ->label('Type')
                                ->required()
                                ->options([
                                    'in' => 'Inbound (Stock In)',
                                    'out' => 'Outbound (Stock Out)',
                                ])
                                ->default('in')
                                ->live(),

                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1),
                        ]),

                    Grid::make(2)
                        ->schema([
                            Select::make('reason')
                                ->label('Reason')
                                ->required()
                                ->options([
                                    'restock' => 'Restock',
                                    'sale' => 'Sale',
                                    'adjustment' => 'Adjustment',
                                    'return' => 'Return',
                                    'damaged' => 'Damaged',
                                    'expired' => 'Expired',
                                    'other' => 'Other',
                                ])
                                ->default('restock'),

                            DateTimePicker::make('transaction_date')
                                ->label('Transaction Date')
                                ->required()
                                ->default(now())
                                ->seconds(false),
                        ]),

                    Textarea::make('note')
                        ->label('Note')
                        ->rows(3)
                        ->helperText('Optional notes about this transaction'),
                ]),

            Section::make('Stockable Item')
                ->schema($stockableFields)
                ->description('Select the product or item this transaction applies to'),
        ]);
    }

    /**
     * Build fields for manual stockable type/id entry.
     *
     * @return array<TextInput>
     */
    private static function buildManualStockableFields(): array
    {
        return [
            TextInput::make('stockable_type')
                ->label('Stockable Type')
                ->required()
                ->helperText('The model class for the stockable item'),

            TextInput::make('stockable_id')
                ->label('Stockable ID')
                ->required()
                ->helperText('The ID of the stockable item'),
        ];
    }

    /**
     * Build fields for dynamic stockable selection from registry.
     *
     * @return array<Grid>
     */
    private static function buildDynamicStockableFields(StockableTypeRegistry $registry): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('stockable_type')
                        ->label('Item Type')
                        ->required()
                        ->options($registry->options())
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('stockable_id', null))
                        ->dehydrateStateUsing(
                            static fn (?string $state): ?string => $state !== null && $state !== ''
                            ? Relation::getMorphAlias($state)
                            : null
                        )
                        ->afterStateHydrated(static function (?string $state, Set $set): void {
                            if ($state !== null && $state !== '') {
                                $morphedModel = Relation::getMorphedModel($state);
                                $set('stockable_type', $morphedModel ?? $state);
                            }
                        }),

                    Select::make('stockable_id')
                        ->label('Item')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(static function (Get $get, ?string $search) use ($registry): array {
                            $stockableType = $get('stockable_type');

                            if (! is_string($stockableType) || $stockableType === '') {
                                return [];
                            }

                            return $registry->search($stockableType, $search);
                        })
                        ->getOptionLabelUsing(static function (Get $get, $value) use ($registry): ?string {
                            $stockableType = $get('stockable_type');

                            if (! is_string($stockableType) || $stockableType === '' || $value === null || $value === '') {
                                return null;
                            }

                            return $registry->resolveLabelForKey($stockableType, $value);
                        })
                        ->hidden(fn (Get $get): bool => ! is_string($get('stockable_type')) || $get('stockable_type') === ''),
                ]),
        ];
    }
}
