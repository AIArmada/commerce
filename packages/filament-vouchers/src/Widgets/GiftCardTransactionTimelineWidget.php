<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Models\GiftCardTransaction;
use Akaunting\Money\Money;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

final class GiftCardTransactionTimelineWidget extends Widget
{
    public ?GiftCard $record = null;

    protected static string $view = 'filament-vouchers::widgets.gift-card-transaction-timeline';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        if ($this->record === null) {
            return ['transactions' => collect()];
        }

        $transactions = $this->record->transactions()
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(function (GiftCardTransaction $transaction): array {
                $type = $transaction->type instanceof GiftCardTransactionType
                    ? $transaction->type
                    : GiftCardTransactionType::from($transaction->type);

                return [
                    'id' => $transaction->id,
                    'type' => $type->label(),
                    'type_value' => $type->value,
                    'amount' => (string) Money::{$this->record->currency}(abs($transaction->amount)),
                    'is_credit' => $transaction->amount >= 0,
                    'balance_after' => (string) Money::{$this->record->currency}($transaction->balance_after),
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at->diffForHumans(),
                    'created_at_full' => $transaction->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return [
            'transactions' => $transactions,
            'currency' => $this->record->currency,
        ];
    }
}
