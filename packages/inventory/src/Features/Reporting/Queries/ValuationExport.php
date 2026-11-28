<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Queries;

use AIArmada\Inventory\Contracts\ExportableInterface;
use AIArmada\Inventory\Features\Valuation\Api\ValuationExportRows;
use Carbon\CarbonImmutable;

/**
 * Export valuation snapshots for financial reporting.
 */
final readonly class ValuationExport implements ExportableInterface
{
    private CarbonImmutable $startDate;

    private CarbonImmutable $endDate;

    public function __construct(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ) {
        $this->endDate = $endDate ?? CarbonImmutable::now();
        $this->startDate = $startDate ?? $this->endDate->subMonths(12);
    }

    public function getHeaders(): array
    {
        return [
            'Snapshot Date',
            'Costing Method',
            'Location',
            'SKU Count',
            'Total Quantity',
            'Total Value',
            'Average Unit Cost',
            'Currency',
            'Variance From Previous',
            'Created At',
        ];
    }

    public function getRows(): iterable
    {
        return app(ValuationExportRows::class)->rows($this->startDate, $this->endDate);
    }

    public function getFilename(): string
    {
        return 'valuation-' . CarbonImmutable::now()->format('Y-m-d-His');
    }
}
