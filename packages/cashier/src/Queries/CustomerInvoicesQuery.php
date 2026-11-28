<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Queries;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CustomerInvoicesQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function getForUser(Model $user): Collection
    {
        if (! $user->exists) {
            return collect();
        }

        if ($user instanceof BillableContract) {
            ActionGuard::assertBillableInScope($user);
        }

        $invoices = collect();
        $detector = app(GatewayDetector::class);

        // Get Stripe invoices
        if ($detector->isAvailable('stripe') && method_exists($user, 'invoices')) {
            try {
                $stripeInvoices = $user->invoices();

                foreach ($stripeInvoices as $invoice) {
                    $invoiceDate = $invoice->date();

                    $invoices->push([
                        'id' => $invoice->id,
                        'gateway' => 'stripe',
                        'number' => $invoice->number ?? $invoice->id,
                        'amount' => $invoice->total(),
                        'date' => $invoiceDate->format('M d, Y'),
                        'status' => $invoice->paid ? 'paid' : 'open',
                        'download_url' => $invoice->invoicePdf(),
                        'sort_timestamp' => $invoiceDate->timestamp,
                    ]);
                }
            } catch (Throwable $e) {
                Log::debug('Failed to retrieve Stripe invoices', ['exception' => $e::class]);
            }
        }

        // Get CHIP invoices
        if ($detector->isAvailable('chip') && method_exists($user, 'chipInvoices')) {
            try {
                $chipInvoices = $user->chipInvoices();

                foreach ($chipInvoices as $invoice) {
                    $createdAt = $invoice->created_at;
                    $currency = (string) ($invoice->currency ?? config('cashier.currency', 'MYR'));

                    $invoices->push([
                        'id' => $invoice->id,
                        'gateway' => 'chip',
                        'number' => $invoice->number ?? $invoice->id,
                        'amount' => MoneyFormatter::formatMinor((int) ($invoice->amount ?? 0), $currency),
                        'date' => $createdAt?->format('M d, Y') ?? 'N/A',
                        'status' => $invoice->status ?? 'unknown',
                        'download_url' => $invoice->pdf_url ?? null,
                        'sort_timestamp' => $createdAt?->timestamp ?? 0,
                    ]);
                }
            } catch (Throwable $e) {
                Log::debug('Failed to retrieve CHIP invoices', ['exception' => $e::class]);
            }
        }

        return $invoices;
    }
}
