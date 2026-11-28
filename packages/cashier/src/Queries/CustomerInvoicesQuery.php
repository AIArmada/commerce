<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Queries;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\UnifiedInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CustomerInvoicesQuery
{
    /** @return Collection<int, InvoiceContract> */
    public function records(Model $user, int $limit = 50): Collection
    {
        if (! $user instanceof BillableContract || ! $user->exists) {
            return collect();
        }

        ActionGuard::assertBillableInScope($user);
        $limit = min(201, max(1, $limit));
        $invoices = collect();

        foreach (app(GatewayDetector::class)->availableGateways() as $gateway) {
            try {
                $records = Cashier::gateway($gateway)->invoices($user, ['limit' => $limit]);
                $invoices = $invoices->merge($records->take($limit));
            } catch (Throwable $e) {
                Log::debug('Failed to retrieve customer invoices.', ['gateway' => $gateway, 'exception' => $e::class]);
            }
        }

        return $invoices->sortByDesc(fn (InvoiceContract $invoice): int => $invoice->date()->getTimestamp())->values();
    }

    /** @return Collection<int, UnifiedInvoice> */
    public function unified(Model $user, int $limit = 50): Collection
    {
        return $this->records($user, $limit)
            ->map(fn (InvoiceContract $invoice): UnifiedInvoice => UnifiedInvoice::fromGateway($invoice, (string) $user->getKey()));
    }

    /** @return Collection<int, array{id: string, gateway: string, number: string, amount: string, date: string, status: string, download_url: string|null}> */
    public function getForUser(Model $user, int $limit = 50): Collection
    {
        return $this->records($user, $limit)->map(fn (InvoiceContract $invoice): array => [
            'id' => $invoice->id(),
            'gateway' => $invoice->gateway(),
            'number' => $invoice->number() ?? $invoice->id(),
            'amount' => $invoice->total(),
            'date' => $invoice->date()->format('M d, Y'),
            'status' => $invoice->status(),
            'download_url' => $invoice->pdfUrl() ?? $invoice->hostedUrl(),
        ]);
    }
}
