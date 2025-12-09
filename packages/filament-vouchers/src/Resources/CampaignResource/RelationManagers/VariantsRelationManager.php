<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\CampaignResource\RelationManagers;

use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use Akaunting\Money\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'A/B Test Variants';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->label('Variant Name')
                    ->required()
                    ->maxLength(100),

                TextInput::make('variant_code')
                    ->label('Code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->helperText('e.g., A, B, C or control, treatment1'),
            ]),

            Grid::make(2)->schema([
                Select::make('voucher_id')
                    ->label('Voucher')
                    ->relationship('voucher', 'code')
                    ->searchable()
                    ->preload()
                    ->helperText('Associate a voucher with this variant'),

                TextInput::make('traffic_percentage')
                    ->label('Traffic %')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(50)
                    ->suffix('%')
                    ->helperText('Percentage of traffic for this variant'),
            ]),

            Toggle::make('is_control')
                ->label('Control Group')
                ->helperText('Mark this as the control variant for comparison')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant_code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_control')
                    ->label('Control')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedMinusCircle),

                TextColumn::make('voucher.code')
                    ->label('Voucher')
                    ->badge()
                    ->color('info')
                    ->placeholder('No voucher'),

                TextColumn::make('traffic_percentage')
                    ->label('Traffic')
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('impressions')
                    ->label('Impressions')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('applications')
                    ->label('Applications')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('conversions')
                    ->label('Conversions')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('conversion_rate')
                    ->label('Conv. Rate')
                    ->state(fn (CampaignVariant $record): string => number_format($record->conversion_rate, 2) . '%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (CampaignVariant $record): string => match (true) {
                        $record->conversion_rate >= 5 => 'success',
                        $record->conversion_rate >= 2 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('revenue_cents')
                    ->label('Revenue')
                    ->formatStateUsing(static function (int $state): string {
                        $currency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));

                        return (string) Money::{$currency}($state);
                    })
                    ->alignEnd()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Variant'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('variant_code', 'asc');
    }
}
