<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Pages;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\UnifiedInvoice;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

final class ListInvoices extends ListRecords
{
    protected static string $resource = UnifiedInvoiceResource::class;

    /**
     * @var Collection<int, UnifiedInvoice>|null
     */
    protected ?Collection $allInvoices = null;

    protected function makeTable(): Table
    {
        $table = parent::makeTable();

        return $table->recordAction(function ($record, Table $table): ?string {
            foreach (['view', 'edit'] as $action) {
                $action = $table->getAction($action);

                if (! $action) {
                    continue;
                }

                $action->record($record);
                $action->getGroup()?->record($record);

                if ($action->isHidden()) {
                    continue;
                }

                if ($action->getUrl()) {
                    continue;
                }

                return $action->getName();
            }

            return null;
        });
    }

    public function getTabs(): array
    {
        $detector = app(GatewayDetector::class);
        $gateways = $detector->availableGateways();

        $tabs = [
            'all' => Tab::make(__('filament-cashier::subscriptions.tabs.all'))
                ->badge(fn () => $this->getAllInvoices()->count()),
        ];

        foreach ($gateways as $gateway) {
            $tabs[$gateway] = Tab::make($detector->getLabel($gateway))
                ->badge(fn () => $this->getAllInvoices()->filter(fn (UnifiedInvoice $invoice): bool => $invoice->gateway === $gateway)->count())
                ->badgeColor($detector->getColor($gateway))
                ->icon($detector->getIcon($gateway));
        }

        return $tabs;
    }

    /**
     * Override to use collection-based records instead of Eloquent.
     */
    public function getTableRecords(): Collection | Paginator | CursorPaginator
    {
        return $this->getFilteredInvoices();
    }

    /**
     * Get table record key.
     */
    public function getTableRecordKey(Model | array | UnifiedInvoice $record): string
    {
        if ($record instanceof Model) {
            return (string) $record->getKey();
        }

        if ($record instanceof UnifiedInvoice) {
            return $record->gateway . '-' . $record->id;
        }

        return (string) ($record['id'] ?? '');
    }

    /**
     * Get all invoices across all gateways through their gateway clients.
     *
     * @return Collection<int, UnifiedInvoice>
     */
    protected function getAllInvoices(): Collection
    {
        if ($this->allInvoices !== null) {
            return $this->allInvoices;
        }

        $user = auth()->user();

        if (! $user instanceof BillableContract || ! $user instanceof Model) {
            $this->allInvoices = collect();

            return $this->allInvoices;
        }

        $invoices = collect();
        $detector = app(GatewayDetector::class);

        foreach ($detector->availableGateways() as $gateway) {
            try {
                $gatewayInvoices = Cashier::gateway($gateway)->invoices($user, ['limit' => 100]);
            } catch (Throwable) {
                continue;
            }

            foreach ($gatewayInvoices->take(100) as $invoice) {
                if ($invoice instanceof InvoiceContract) {
                    $invoices->push(UnifiedInvoice::fromGateway($invoice, (string) $user->getKey()));
                }
            }
        }

        $this->allInvoices = $invoices->sortByDesc('date')->values();

        return $this->allInvoices;
    }

    /**
     * Filter invoices based on active tab.
     *
     * @return Collection<int, UnifiedInvoice>
     */
    protected function getFilteredInvoices(): Collection
    {
        $invoices = $this->getAllInvoices();
        $activeTab = $this->activeTab;

        if ($activeTab && $activeTab !== 'all') {
            $invoices = $invoices->filter(fn (UnifiedInvoice $invoice): bool => $invoice->gateway === $activeTab);
        }

        // Apply filters from filter form
        $filterData = $this->tableFilters ?? [];

        if (isset($filterData['gateway']['value']) && $filterData['gateway']['value']) {
            $gateway = $filterData['gateway']['value'];
            $invoices = $invoices->filter(fn (UnifiedInvoice $invoice): bool => $invoice->gateway === $gateway);
        }

        if (isset($filterData['status']['value']) && $filterData['status']['value']) {
            $invoices = $invoices->filter(
                fn (UnifiedInvoice $inv) => $inv->status->value === $filterData['status']['value']
            );
        }

        return $invoices->values();
    }
}
