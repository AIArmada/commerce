<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class WishlistsRelationManager extends RelationManager
{
    protected static string $relationship = 'wishlists';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Wishlist Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2),

                Forms\Components\Toggle::make('is_public')
                    ->label('Public')
                    ->helperText('Allow sharing via link'),

                Forms\Components\Toggle::make('is_default')
                    ->label('Default Wishlist'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Wishlist')
                    ->searchable()
                    ->description(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public'),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('view_items')
                    ->label('Items')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Items in {$record->name}")
                    ->modalContent(function ($record) {
                        $items = $record->items()->with('product')->get();

                        if ($items->isEmpty()) {
                            return 'No items in this wishlist.';
                        }

                        return view('filament-customers::wishlist-items', ['items' => $items]);
                    }),
                \Filament\Actions\Action::make('copy_link')
                    ->label('Copy Link')
                    ->icon('heroicon-o-link')
                    ->action(function ($record): void {
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Link Copied')
                            ->body($record->getShareUrl())
                            ->send();
                    })
                    ->visible(fn ($record) => $record->is_public),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
