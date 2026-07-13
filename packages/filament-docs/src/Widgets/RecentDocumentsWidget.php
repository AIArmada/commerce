<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\DocStatus;
use AIArmada\FilamentDocs\Resources\DocResource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class RecentDocumentsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function getTableHeading(): ?string
    {
        return 'Recent Documents';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OwnerUiScope::apply(Doc::query(), includeGlobal: false)
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('doc_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('doc_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('customer_data.name')
                    ->label('Customer')
                    ->default('-'),

                TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn (int|string $state, Doc $record): string => MoneyFormatter::formatMinor((int) $state, $record->currency))
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (DocStatus $state): string => $state->color()),

                TextColumn::make('issue_date')
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (Doc $record): string => DocResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-o-eye'),
            ])
            ->paginated(false);
    }
}
