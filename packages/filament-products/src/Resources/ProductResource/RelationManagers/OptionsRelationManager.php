<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\Products\Actions\ReplaceOptionValues;
use AIArmada\Products\Actions\SaveOption;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class OptionsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'options';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Product && $ownerRecord->supportsVariants();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Option Name')
                    ->required()
                    ->placeholder('e.g., Size, Color, Material')
                    ->maxLength(100),

                TextInput::make('display_name')
                    ->label('Display Name')
                    ->placeholder('e.g., Select your size')
                    ->maxLength(255),

                TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Select::make('visibility')
                    ->label('Visibility')
                    ->options(['visible' => 'Visible', 'hidden' => 'Hidden'])
                    ->default('visible'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('position')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Option Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Display Name')
                    ->placeholder('Not set'),

                Tables\Columns\TextColumn::make('values_count')
                    ->label('Values')
                    ->counts('values')
                    ->sortable(),

                Tables\Columns\TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'hidden' ? 'gray' : 'success')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data): Option => app(SaveOption::class)->create($this->catalogProduct(), $data)),
            ])
            ->actions([
                Action::make('manage_values')
                    ->label('Values')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Manage Values for {$record->name}")
                    ->modalWidth('lg')
                    ->form([
                        Repeater::make('option_values')
                            ->label('Option Values')
                            ->schema([
                                Hidden::make('id'),

                                TextInput::make('name')
                                    ->label('Value')
                                    ->required()
                                    ->placeholder('e.g., Small, Red'),

                                ColorPicker::make('swatch_color')
                                    ->label('Swatch Color'),

                                TextInput::make('position')
                                    ->label('Position')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->reorderable()
                            ->defaultItems(0)
                            ->addActionLabel('Add Value'),
                    ])
                    ->fillForm(fn ($record) => [
                        'option_values' => $record->values->map(fn ($v) => [
                            'id' => $v->id,
                            'name' => $v->name,
                            'swatch_color' => $v->swatch_color,
                            'position' => $v->position,
                        ])->toArray(),
                    ])
                    ->action(function ($record, array $data): void {
                        if (isset($this->ownerRecord)) {
                            $ownerRecord = $this->getOwnerRecord();

                            if ($ownerRecord instanceof Product) {
                                Gate::authorize('update', $ownerRecord);
                            }
                        }

                        abort_unless($record instanceof Option && $record->product_id === $this->getOwnerRecord()->getKey(), 404);

                        app(ReplaceOptionValues::class)->execute($record, array_values($data['option_values'] ?? []));

                        Notification::make()
                            ->success()
                            ->title('Option values updated')
                            ->send();
                    }),
                EditAction::make()
                    ->using(fn (Option $record, array $data): Option => app(SaveOption::class)->update($this->catalogProduct(), $record, $data)),
                DeleteAction::make()
                    ->using(fn (Option $record): bool => app(SaveOption::class)->delete($this->catalogProduct(), $record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function catalogProduct(): Product
    {
        $product = $this->getOwnerRecord();
        abort_unless($product instanceof Product, 404);

        return $product;
    }
}
