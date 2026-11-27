<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\Pages;

use AIArmada\Cart\Actions\ClearLiveCart;
use AIArmada\Cart\Actions\DestroyLiveCart;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot as Cart;
use AIArmada\FilamentCart\EntryPoints\Http\DownloadCart;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;
use AIArmada\FilamentVouchers\Widgets\AppliedVouchersWidget;
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

final class ViewCart extends ViewRecord
{
    protected static string $resource = CartResource::class;

    public function getTitle(): string
    {
        \assert($this->record instanceof Cart);

        return 'Cart: ' . $this->record->identifier;
    }

    public function getSubheading(): string
    {
        \assert($this->record instanceof Cart);

        if ($this->record->isEmpty()) {
            return 'This cart is empty';
        }

        $itemCount = $this->record->items_count;
        $totalQty = $this->record->quantity;

        $summary = "{$itemCount} " . str('item')->plural($itemCount) .
            " ({$totalQty} " . str('unit')->plural($totalQty) . ')';

        $summary .= ' • Subtotal ' . $this->record->formatMoney($this->record->subtotal);

        if ($this->record->savings > 0) {
            $summary .= ' • Savings ' . $this->record->formatMoney($this->record->savings);
        }

        $summary .= ' • Total ' . $this->record->formatMoney($this->record->total);

        return $summary;
    }

    protected function getHeaderActions(): array
    {
        \assert($this->record instanceof Cart);

        $actions = [];

        // Add voucher management actions if filament-vouchers is available
        if (class_exists(CartVoucherActions::class)) {
            $actions[] = CartVoucherActions::applyVoucher();
            $actions[] = CartVoucherActions::showAppliedVouchers();
        }

        $actions[] = Actions\Action::make('clear_cart')
            ->label('Clear Cart')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Clear Cart')
            ->modalDescription('Are you sure you want to clear all items from this cart? This action cannot be undone.')
            ->action(function (): void {
                \assert($this->record instanceof Cart);
                app(ClearLiveCart::class)->execute($this->record);
                $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
            })
            ->visible(fn (): bool => $this->record instanceof Cart && ! $this->record->isEmpty());

        $actions[] = Actions\Action::make('export_cart')
            ->label('Export Cart')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('info')
            ->action(function () {
                \assert($this->record instanceof Cart);

                return app(DownloadCart::class)->download($this->record);
            });

        $actions[] = Actions\Action::make('delete_cart')
            ->label('Delete Cart')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Cart')
            ->modalDescription('This will delete the live cart and its synchronized snapshot.')
            ->action(function (): void {
                \assert($this->record instanceof Cart);
                app(DestroyLiveCart::class)->execute($this->record);
                $this->redirect($this->getResource()::getUrl('index'));
            });

        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // Add voucher widgets if filament-vouchers is available
        if (class_exists(AppliedVouchersWidget::class)) {
            $widgets[] = AppliedVouchersWidget::class;
        }

        return $widgets;
    }

    protected function getFooterWidgets(): array
    {
        $widgets = [];

        // Add voucher management widgets if filament-vouchers is available
        if (class_exists(QuickApplyVoucherWidget::class)) {
            $widgets[] = QuickApplyVoucherWidget::class;
        }

        if (class_exists(VoucherSuggestionsWidget::class)) {
            $widgets[] = VoucherSuggestionsWidget::class;
        }

        return $widgets;
    }
}
