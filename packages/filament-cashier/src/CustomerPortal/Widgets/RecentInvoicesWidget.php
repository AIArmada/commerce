<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Widgets;

use AIArmada\Cashier\Queries\CustomerInvoicesQuery;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class RecentInvoicesWidget extends Widget
{
    /** @var view-string */
    protected string $view = 'filament-cashier::customer-portal.widgets.recent-invoices';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 3;

    /**
     * @return Collection<int, array{id: string, gateway: string, amount: string, date: string, status: string}>
     */
    public function getRecentInvoices(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            return collect();
        }

        return app(CustomerInvoicesQuery::class)->getForUser($user, 5)
            ->take(5)
            ->map(static fn (array $invoice): array => [
                'id' => $invoice['id'],
                'gateway' => $invoice['gateway'],
                'amount' => $invoice['amount'],
                'date' => $invoice['date'],
                'status' => $invoice['status'],
            ])
            ->values();
    }
}
