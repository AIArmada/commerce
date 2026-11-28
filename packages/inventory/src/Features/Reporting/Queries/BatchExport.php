<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Queries;

use AIArmada\Inventory\Contracts\ExportableInterface;
use AIArmada\Inventory\Features\Traceability\Api\BatchExportRows;
use Carbon\CarbonImmutable;

/**
 * Export batch/lot information.
 */
final readonly class BatchExport implements ExportableInterface
{
    public function __construct(
        private ?string $status = null,
        private bool $expiringOnly = false,
        private int $expiringWithinDays = 30,
    ) {}

    public function getHeaders(): array
    {
        return [
            'Batch Number',
            'SKU Type',
            'SKU ID',
            'Location',
            'Quantity',
            'Status',
            'Unit Cost',
            'Total Value',
            'Manufactured Date',
            'Expiry Date',
            'Days Until Expiry',
            'Supplier',
            'Purchase Order',
            'Notes',
            'Created At',
        ];
    }

    public function getRows(): iterable
    {
        return app(BatchExportRows::class)->rows($this->status, $this->expiringOnly, $this->expiringWithinDays);
    }

    public function getFilename(): string
    {
        $suffix = $this->expiringOnly ? '-expiring' : '';

        return 'batches' . $suffix . '-' . CarbonImmutable::now()->format('Y-m-d-His');
    }
}
