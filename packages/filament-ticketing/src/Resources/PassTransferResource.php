<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Models\PassTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class PassTransferResource extends Resource
{
    protected static ?string $model = PassTransfer::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-ticketing.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-ticketing.resources.navigation_sort.pass_transfer');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('pass', fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false))
            ->with(['pass', 'fromHolder', 'toHolder']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pass.pass_no')->label('Pass No.')->searchable(),
                Tables\Columns\TextColumn::make('fromHolder.name')->label('From'),
                Tables\Columns\TextColumn::make('toHolder.name')->label('To'),
                Tables\Columns\TextColumn::make('reason'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => PassTransferResource\Pages\ListPassTransfers::route('/'),
        ];
    }
}
