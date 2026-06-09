<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxClassResource\Tables;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Tax\Models\TaxClass;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class TaxClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tax Class')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->color('gray'),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->toggleable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('position')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('delete')
                    ->authorize(fn (): bool => auth()->user()?->can('tax.classes.delete') ?? false)
                    ->label('Delete Selected')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        foreach ($records as $record) {
                            $verified = OwnerWriteGuard::findOrFailForOwner(
                                TaxClass::class,
                                $record->getKey(),
                                includeGlobal: false,
                                message: 'Tax class is not accessible in the current owner scope.',
                            );

                            $verified->delete();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
