<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Widgets;

use AIArmada\Stock\Models\StockTransaction;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;

/**
 * Displays stock transaction history as a timeline.
 * Can be used standalone or on a stockable record's view page.
 */
#[Lazy]
final class StockTransactionTimelineWidget extends Widget
{
    public ?Model $record = null;

    /** @phpstan-ignore-next-line */
    protected string $view = 'filament-stock::widgets.stock-transaction-timeline';

    protected int | string | array $columnSpan = 'full';

    /**
     * Get timeline events from stock transaction history.
     *
     * @return Collection<int, array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     description: string,
     *     timestamp: \Carbon\Carbon,
     *     timestamp_human: string,
     *     icon: string,
     *     color: string,
     *     details: array<string, mixed>
     * }>
     */
    public function getTimelineEvents(): Collection
    {
        $query = StockTransaction::query()
            ->with('user')
            ->orderBy('transaction_date', 'desc')
            ->limit(50);

        // If we have a record context, filter by stockable
        if ($this->record !== null) {
            $query->where('stockable_type', $this->record->getMorphClass())
                ->where('stockable_id', $this->record->getKey());
        }

        $transactions = $query->get();

        return $transactions->map(fn (StockTransaction $transaction) => $this->buildTimelineEvent($transaction));
    }

    /**
     * Get summary statistics.
     *
     * @return array{total_transactions: int, total_in: int, total_out: int, net_change: int}
     */
    public function getSummaryStats(): array
    {
        $query = StockTransaction::query();

        if ($this->record !== null) {
            $query->where('stockable_type', $this->record->getMorphClass())
                ->where('stockable_id', $this->record->getKey());
        }

        $transactions = $query->get();

        $totalIn = $transactions->where('type', 'in')->sum('quantity');
        $totalOut = $transactions->where('type', 'out')->sum('quantity');

        return [
            'total_transactions' => $transactions->count(),
            'total_in' => (int) $totalIn,
            'total_out' => (int) $totalOut,
            'net_change' => (int) ($totalIn - $totalOut),
        ];
    }

    /**
     * Build a timeline event from a transaction.
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     description: string,
     *     timestamp: \Carbon\Carbon,
     *     timestamp_human: string,
     *     icon: string,
     *     color: string,
     *     details: array<string, mixed>
     * }
     */
    protected function buildTimelineEvent(StockTransaction $transaction): array
    {
        $isInbound = $transaction->type === 'in';
        $quantity = $transaction->quantity;

        $title = $isInbound
            ? "Stock In: +{$quantity}"
            : "Stock Out: -{$quantity}";

        $description = ucfirst($transaction->reason ?? 'Unknown reason');

        if ($transaction->note) {
            $description .= " • {$transaction->note}";
        }

        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'title' => $title,
            'description' => $description,
            'timestamp' => $transaction->transaction_date,
            'timestamp_human' => $transaction->transaction_date->diffForHumans(),
            'icon' => $this->getEventIcon($transaction),
            'color' => $isInbound ? 'success' : 'danger',
            'details' => [
                'quantity' => $quantity,
                'reason' => $transaction->reason,
                'note' => $transaction->note,
                'user' => $transaction->user->name ?? 'System',
                'stockable_type' => class_basename($transaction->stockable_type),
                'stockable_id' => $transaction->stockable_id,
            ],
        ];
    }

    /**
     * Get icon for event based on transaction details.
     */
    protected function getEventIcon(StockTransaction $transaction): string
    {
        if ($transaction->type === 'in') {
            return match ($transaction->reason) {
                'restock' => 'heroicon-o-arrow-down-tray',
                'return' => 'heroicon-o-arrow-uturn-left',
                'adjustment' => 'heroicon-o-adjustments-horizontal',
                default => 'heroicon-o-arrow-up',
            };
        }

        return match ($transaction->reason) {
            'sale' => 'heroicon-o-shopping-cart',
            'damaged' => 'heroicon-o-exclamation-triangle',
            'expired' => 'heroicon-o-clock',
            'adjustment' => 'heroicon-o-adjustments-horizontal',
            default => 'heroicon-o-arrow-down',
        };
    }
}
