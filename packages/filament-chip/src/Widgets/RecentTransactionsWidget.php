<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class RecentTransactionsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Transactions';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getRecentTransactionsQuery())
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('client_email')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('formatted_total')
                    ->label('Amount')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Purchase $record): string => $record->statusColor())
                    ->formatStateUsing(fn (Purchase $record): string => $record->statusBadge()),

                TextColumn::make('created_on')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Purchase $record): string => $this->getResourceViewUrl($record))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }

    /**
     * @return Builder<Purchase>
     */
    protected function getRecentTransactionsQuery(): Builder
    {
        /** @var Builder<Purchase> $query */
        $query = $this->withResolvedOwnerOrExplicitGlobal(function (): Builder {
            return tap(Purchase::query(), function ($query): void {
                if (method_exists($query->getModel(), 'scopeForOwner')) {
                    $query->forOwner();
                }
            })
                ->where('is_test', false)
                ->orderBy('created_on', 'desc')
                ->limit(10);
        });

        return $query;
    }

    private function getResourceViewUrl(Purchase $record): string
    {
        $panelId = Filament::getCurrentPanel()?->getId() ?? 'admin';

        return route("filament.{$panelId}.resources.purchases.view", ['record' => $record]);
    }

    private function withResolvedOwnerOrExplicitGlobal(callable $callback): mixed
    {
        if (OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal()) {
            return $callback();
        }

        return OwnerContext::withOwner(null, static fn (): mixed => $callback());
    }
}
