<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Widgets;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use Exception;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

final class RecentInvoicesWidget extends Widget
{
    protected string $view = 'filament-cashier::customer-portal.widgets.recent-invoices';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 3;

    /**
     * @return Collection<int, array{id: string, gateway: string, amount: string, date: string, status: string}>
     */
    public function getRecentInvoices(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        $invoices = collect();
        $detector = app(GatewayDetector::class);

        // Get recent Stripe invoices
        if ($detector->isAvailable('stripe') && method_exists($user, 'invoices')) {
            try {
                $stripeInvoices = $user->invoices(['limit' => 3]);

                foreach ($stripeInvoices as $invoice) {
                    $invoices->push([
                        'id' => $invoice->id,
                        'gateway' => 'stripe',
                        'amount' => $invoice->total(),
                        'date' => $invoice->date()->format('M d, Y'),
                        'status' => $invoice->paid ? 'paid' : 'open',
                    ]);
                }
            } catch (Exception) {
                // Silently fail
            }
        }

        // Get recent CHIP invoices
        if ($detector->isAvailable('chip') && method_exists($user, 'chipInvoices')) {
            try {
                $chipInvoices = $user->chipInvoices(3);

                foreach ($chipInvoices as $invoice) {
                    $invoices->push([
                        'id' => $invoice->id,
                        'gateway' => 'chip',
                        'amount' => 'RM '.number_format(($invoice->amount ?? 0) / 100, 2),
                        'date' => $invoice->created_at?->format('M d, Y') ?? 'N/A',
                        'status' => $invoice->status ?? 'unknown',
                    ]);
                }
            } catch (Exception) {
                // Silently fail
            }
        }

        return $invoices->take(5);
    }
}
