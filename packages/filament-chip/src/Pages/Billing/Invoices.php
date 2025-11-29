<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages\Billing;

use AIArmada\FilamentChip\Concerns\InteractsWithBillable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class Invoices extends Page
{
    use InteractsWithBillable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament-chip::pages.billing.invoices';

    public static function getNavigationLabel(): string
    {
        return __('Invoices');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-chip.billing.features.invoices', true);
    }

<<<<<<< Updated upstream
<<<<<<< Updated upstream
    public function getTitle(): string|Htmlable
    {
        return __('Billing History');
    }

=======
    /**
     * @return array<string, mixed>
     */
>>>>>>> Stashed changes
=======
    /**
     * @return array<string, mixed>
     */
>>>>>>> Stashed changes
    public function getViewData(): array
    {
        return [
            'billable' => $this->getBillable(),
            'invoices' => $this->getInvoices(),
        ];
    }

<<<<<<< Updated upstream
<<<<<<< Updated upstream
    public function downloadInvoice(string $invoiceId): mixed
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'findInvoice')) {
            abort(404);
        }

        $invoice = $billable->findInvoice($invoiceId);

        if (! $invoice) {
            abort(404);
        }

        return $invoice->download([
            'vendor' => config('filament-chip.billing.invoice.vendor_name', config('app.name')),
            'product' => config('filament-chip.billing.invoice.product_name', 'Subscription'),
        ]);
    }

    public function formatInvoiceStatus(string $status): string
    {
        $statuses = [
            'paid' => __('Paid'),
            'open' => __('Open'),
            'void' => __('Void'),
            'uncollectible' => __('Uncollectible'),
            'draft' => __('Draft'),
        ];

        return $statuses[mb_strtolower($status)] ?? ucfirst($status);
    }

    public function getStatusColor(string $status): string
    {
        return match (mb_strtolower($status)) {
            'paid' => 'success',
            'open' => 'warning',
            'void', 'uncollectible' => 'danger',
            default => 'gray',
        };
    }

    protected function getBillable(): mixed
    {
        $user = filament()->auth()->user();

        if (! $user) {
            return null;
        }

        $billableModel = config('filament-chip.billing.billable_model');

        if ($billableModel && $user instanceof $billableModel) {
            return $user;
        }

        if (method_exists($user, 'currentTeam')) {
            return $user->currentTeam;
        }

        return $user;
    }

=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
    /**
     * @return Collection<int, mixed>
     */
    protected function getInvoices(): Collection
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'invoices')) {
            return collect();
        }

        return $billable->invoices();
    }
<<<<<<< Updated upstream
=======

    public function downloadInvoice(string $invoiceId): Response
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'findInvoice')) {
            abort(404);
        }

        $invoice = $billable->findInvoice($invoiceId);

        if (! $invoice) {
            abort(404);
        }

        return $invoice->download([
            'vendor' => config('filament-chip.billing.invoice.vendor_name', config('app.name')),
            'product' => config('filament-chip.billing.invoice.product_name', 'Subscription'),
        ]);
    }

    public function formatInvoiceStatus(string $status): string
    {
        $statuses = [
            'paid' => __('Paid'),
            'open' => __('Open'),
            'void' => __('Void'),
            'uncollectible' => __('Uncollectible'),
            'draft' => __('Draft'),
        ];

        return $statuses[strtolower($status)] ?? ucfirst($status);
    }

    public function getStatusColor(string $status): string
    {
        return match (strtolower($status)) {
            'paid' => 'success',
            'open' => 'warning',
            'void', 'uncollectible' => 'danger',
            default => 'gray',
        };
    }
>>>>>>> Stashed changes
}
