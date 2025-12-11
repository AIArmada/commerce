<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources;

use AIArmada\FilamentTax\Resources\TaxRateResource\Pages;
use AIArmada\Tax\Models\TaxRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Rate Details')
                            ->schema([
                                Forms\Components\Select::make('zone_id')
                                    ->label('Tax Zone')
                                    ->relationship('zone', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required(),
                                        Forms\Components\TextInput::make('code')->required(),
                                    ]),

                                Forms\Components\TextInput::make('name')
                                    ->label('Rate Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., SST, VAT, GST')
                                    ->helperText('Descriptive name for this tax rate'),

                                Forms\Components\Select::make('tax_class')
                                    ->label('Tax Class')
                                    ->options([
                                        'standard' => 'Standard',
                                        'reduced' => 'Reduced',
                                        'zero' => 'Zero Rate',
                                        'exempt' => 'Exempt',
                                    ])
                                    ->default('standard')
                                    ->required(),

                                Forms\Components\TextInput::make('rate')
                                    ->label('Tax Rate')
                                    ->numeric()
                                    ->suffix('%')
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0)
                                    ->helperText('Tax percentage (e.g., 6 for 6%)'),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Application Rules')
                            ->schema([
                                Forms\Components\Toggle::make('is_compound')
                                    ->label('Compound Tax')
                                    ->helperText('Calculate this tax on top of other taxes')
                                    ->default(false)
                                    ->inline(false),

                                Forms\Components\Toggle::make('is_shipping')
                                    ->label('Apply to Shipping')
                                    ->helperText('Include shipping costs in tax calculation')
                                    ->default(true)
                                    ->inline(false),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher priority rates are applied first')
                                    ->minValue(0),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Enable/disable this tax rate'),

                                Forms\Components\Placeholder::make('rate_info')
                                    ->label('Effective Rate')
                                    ->content(function ($record, Forms\Get $get) {
                                        $rate = $get('rate') ?? $record?->rate ?? 0;

                                        return number_format($rate, 2) . '%';
                                    }),

                                Forms\Components\Placeholder::make('created_info')
                                    ->label('Created')
                                    ->content(fn ($record) => $record?->created_at?->diffForHumans() ?? 'Not created yet'),
                            ]),

                        Forms\Components\Section::make('Additional Info')
                            ->schema([
                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->helperText('Optional description or notes'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Zone')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Rate Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('tax_class')
                    ->label('Class')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'standard' => 'success',
                        'reduced' => 'warning',
                        'zero' => 'gray',
                        'exempt' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('rate')
                    ->label('Rate')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('is_compound')
                    ->label('Compound')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_shipping')
                    ->label('Shipping')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship('zone', 'name'),

                Tables\Filters\SelectFilter::make('tax_class')
                    ->label('Class')
                    ->options([
                        'standard' => 'Standard',
                        'reduced' => 'Reduced',
                        'zero' => 'Zero Rate',
                        'exempt' => 'Exempt',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('is_compound')
                    ->label('Compound'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxRates::route('/'),
            'create' => Pages\CreateTaxRate::route('/create'),
            'view' => Pages\ViewTaxRate::route('/{record}'),
            'edit' => Pages\EditTaxRate::route('/{record}/edit'),
        ];
    }
}
