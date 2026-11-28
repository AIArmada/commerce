<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Queries;

use AIArmada\Inventory\Contracts\ExportableInterface;
use AIArmada\Inventory\Features\Stock\Api\StockExportRows;
use Carbon\CarbonImmutable;

/**
 * Export stock levels to various formats.
 */
final readonly class StockLevelExport implements ExportableInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private array $filters = [],
    ) {}

    public function getHeaders(): array
    {
        return [
            'SKU Type',
            'SKU ID',
            'Location',
            'Quantity On Hand',
            'Reserved',
            'Available',
            'Safety Stock',
            'Max Stock',
            'Reorder Point',
            'Status',
            'Last Updated',
        ];
    }

    public function getRows(): iterable
    {
        return app(StockExportRows::class)->levels($this->filters);
    }

    public function getFilename(): string
    {
        return 'stock-levels-' . CarbonImmutable::now()->format('Y-m-d-His');
    }
}
