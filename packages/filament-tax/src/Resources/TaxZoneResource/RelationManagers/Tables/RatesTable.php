<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\Tables;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentTax\Support\FilamentTaxAuthz;
use AIArmada\Tax\Models\TaxRate;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

final class RatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Rate Name')
                    ->searchable(),

                TextColumn::make('tax_class')
                    ->label('Class')
                    ->badge(),

                TextColumn::make('rate')
                    ->label('Rate')
                    ->formatStateUsing(fn ($state) => number_format((float) $state / 100, 2) . '%')
                    ->alignEnd(),

                IconColumn::make('is_compound')
                    ->label('Compound')
                    ->boolean(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                FilamentTaxAuthz::requirePermission(
                    BulkAction::make('delete')
                        ->label('Delete Selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorizeIndividualRecords('delete')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $verified = OwnerWriteGuard::findOrFailForOwner(
                                    TaxRate::class,
                                    $record->getKey(),
                                    includeGlobal: false,
                                    message: 'Tax rate is not accessible in the current owner scope.',
                                );

                                $verified->delete();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    'tax.rates.delete',
                ),
            ]);
    }
}
