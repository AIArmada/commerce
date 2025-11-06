<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\Pages;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

use function assert;

final class ViewCart extends ViewRecord
{
    protected static string $resource = CartResource::class;

    public function getTitle(): string
    {
        /** @phpstan-ignore-next-line */
        return 'Cart: '.$this->record->identifier;
    }

    public function getSubheading(): string
    {
        /** @phpstan-ignore-next-line */
        if ($this->record->isEmpty()) {
            return 'This cart is empty';
        }

        /** @phpstan-ignore-next-line */
        $itemCount = $this->record->items_count;
        /** @phpstan-ignore-next-line */
        $totalQty = $this->record->quantity;

        $summary = "{$itemCount} ".str('item')->plural($itemCount).
            " ({$totalQty} ".str('unit')->plural($totalQty).')';

        /** @phpstan-ignore-next-line */
        $summary .= ' • Subtotal '.$this->record->formatMoney($this->record->subtotal);

        /** @phpstan-ignore-next-line */
        if ($this->record->savings > 0) {
            /** @phpstan-ignore-next-line */
            $summary .= ' • Savings '.$this->record->formatMoney($this->record->savings);
        }

        /** @phpstan-ignore-next-line */
        $summary .= ' • Total '.$this->record->formatMoney($this->record->total);

        return $summary;
    }

    protected function getHeaderActions(): array
    {
        assert($this->record instanceof Cart);

        $actions = [
            Actions\EditAction::make()
                ->icon(Heroicon::OutlinedPencil),
        ];

        // Add voucher management actions if filament-vouchers is available
        if (class_exists(\AIArmada\FilamentVouchers\Extensions\CartVoucherActions::class)) {
            $actions[] = \AIArmada\FilamentVouchers\Extensions\CartVoucherActions::applyVoucher();
            $actions[] = \AIArmada\FilamentVouchers\Extensions\CartVoucherActions::showAppliedVouchers();
        }

        $actions[] = Actions\Action::make('clear_cart')
            ->label('Clear Cart')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Clear Cart')
            ->modalDescription('Are you sure you want to clear all items from this cart? This action cannot be undone.')
            ->action(function (): void {
                /** @var Cart $record */
                $record = $this->record;
                app(CartInstanceManager::class)
                    ->resolve($record->instance, $record->identifier)
                    ->clear();
                $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
            })
            /** @phpstan-ignore-next-line */
            ->visible(fn () => ! $this->record->isEmpty());

        $actions[] = Actions\Action::make('export_cart')
            ->label('Export Cart')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('info')
            ->action(function () {
                /** @var Cart $record */
                $record = $this->record;

                return response()->download(
                    storage_path('app/temp/cart_'.$record->identifier.'.json'),
                    'cart_'.$record->identifier.'.json',
                    ['Content-Type' => 'application/json']
                );
            })
            ->before(function (): void {
                // Create the export file
                /** @var Cart $record */
                $record = $this->record;
                $cartData = [
                    'identifier' => $record->identifier,
                    'instance' => $record->instance,
                    'items' => $record->items,
                    'conditions' => $record->conditions,
                    'metadata' => $record->metadata,
                    'exported_at' => now()->toISOString(),
                ];

                if (! file_exists(storage_path('app/temp'))) {
                    mkdir(storage_path('app/temp'), 0755, true);
                }

                file_put_contents(
                    storage_path('app/temp/cart_'.$record->identifier.'.json'),
                    json_encode($cartData, JSON_PRETTY_PRINT)
                );
            });

        $actions[] = Actions\DeleteAction::make()
            ->icon(Heroicon::OutlinedTrash);

        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // Add voucher widgets if filament-vouchers is available
        if (class_exists(\AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget::class)) {
            $widgets[] = \AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget::class;
        }

        return $widgets;
    }

    protected function getFooterWidgets(): array
    {
        $widgets = [];

        // Add voucher management widgets if filament-vouchers is available
        if (class_exists(\AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget::class)) {
            $widgets[] = \AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget::class;
        }

        if (class_exists(\AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget::class)) {
            $widgets[] = \AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget::class;
        }

        return $widgets;
    }
}
