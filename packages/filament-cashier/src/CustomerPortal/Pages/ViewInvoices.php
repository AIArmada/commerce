<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Pages;

use AIArmada\Cashier\Queries\CustomerInvoicesQuery;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ViewInvoices extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    /** @var view-string */
    protected string $view = 'filament-cashier::customer-portal.view-invoices';

    public int $limit = 50;

    public bool $hasMoreInvoices = false;

    private const int DEFAULT_LOAD_MORE_INCREMENT = 50;

    private const int MAX_LOAD_MORE_INCREMENT = 50;

    private const int MAX_LIMIT = 200;

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::portal.invoices.title');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.billing_portal.navigation_sort.invoices');
    }

    public function getTitle(): string
    {
        return __('filament-cashier::portal.invoices.title');
    }

    public function loadMoreInvoices(int $increment = self::DEFAULT_LOAD_MORE_INCREMENT): void
    {
        $this->limit = min(
            self::MAX_LIMIT,
            $this->limit + min(max(1, $increment), self::MAX_LOAD_MORE_INCREMENT),
        );
    }

    /**
     * @return Collection<int, array{id: string, gateway: string, number: string, amount: string, date: string, status: string, download_url: string|null}>
     */
    public function getInvoices(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            $this->hasMoreInvoices = false;

            return collect();
        }

        $invoices = app(CustomerInvoicesQuery::class)->getForUser($user);

        $limit = min(max(1, $this->limit), self::MAX_LIMIT);
        $sorted = $invoices->sortByDesc('sort_timestamp')->values();
        $this->hasMoreInvoices = $sorted->count() > $limit;

        /** @var Collection<int, array{id: string, gateway: string, number: string, amount: string, date: string, status: string, download_url: string|null}> $result */
        $result = $sorted
            ->take($limit)
            ->map(function (array $invoice): array {
                return [
                    'id' => (string) $invoice['id'],
                    'gateway' => (string) $invoice['gateway'],
                    'number' => (string) $invoice['number'],
                    'amount' => (string) $invoice['amount'],
                    'date' => (string) $invoice['date'],
                    'status' => (string) $invoice['status'],
                    'download_url' => is_string($invoice['download_url'] ?? null) ? $invoice['download_url'] : null,
                ];
            })
            ->values();

        return $result;
    }
}
