<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Queries;

use AIArmada\Inventory\Contracts\ExportableInterface;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Api\StockExportRows;
use Carbon\CarbonImmutable;

/**
 * Export inventory movements to various formats.
 */
final class MovementExport implements ExportableInterface
{
    public function __construct(
        private ?CarbonImmutable $startDate = null,
        private ?CarbonImmutable $endDate = null,
        private ?MovementType $movementType = null,
        private ?string $locationId = null,
    ) {
        $this->startDate ??= CarbonImmutable::now()->subMonth();
        $this->endDate ??= CarbonImmutable::now();
    }

    public function getHeaders(): array
    {
        return [
            'ID',
            'Type',
            'SKU Type',
            'SKU ID',
            'From Location',
            'To Location',
            'Quantity',
            'Reason',
            'Reference',
            'Note',
            'Occurred At',
            'Created At',
        ];
    }

    public function getRows(): iterable
    {
        return app(StockExportRows::class)->movements(
            $this->startDate ?? CarbonImmutable::now()->subMonth(),
            $this->endDate ?? CarbonImmutable::now(),
            $this->movementType?->value,
            $this->locationId,
        );
    }

    public function getFilename(): string
    {
        $suffix = $this->movementType !== null ? "-{$this->movementType->value}" : '';

        return 'movements' . $suffix . '-' . CarbonImmutable::now()->format('Y-m-d-His');
    }
}
