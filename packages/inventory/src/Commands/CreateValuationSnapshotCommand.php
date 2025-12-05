<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Commands;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Services\ValuationService;
use Illuminate\Console\Command;

class CreateValuationSnapshotCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'inventory:valuation-snapshot
                            {--method=fifo : The costing method to use (fifo, weighted_average, standard)}
                            {--location= : Optional location ID to snapshot}
                            {--all-locations : Create snapshots for all locations}';

    /**
     * @var string
     */
    protected $description = 'Create inventory valuation snapshots';

    public function handle(ValuationService $valuationService): int
    {
        $methodValue = $this->option('method');
        $method = CostingMethod::tryFrom($methodValue);

        if ($method === null) {
            $this->error("Invalid costing method: {$methodValue}");
            $this->info('Valid methods: fifo, weighted_average, standard');

            return self::FAILURE;
        }

        $locationId = $this->option('location');
        $allLocations = $this->option('all-locations');

        $this->info("Creating valuation snapshot using {$method->label()}...");

        if ($allLocations) {
            $snapshots = $valuationService->createDailySnapshots($method);

            $this->info("Created {$snapshots->count()} snapshots:");

            foreach ($snapshots as $snapshot) {
                $locationName = $snapshot->location_id
                    ? $snapshot->location->name ?? 'Unknown'
                    : 'All Locations';

                $this->line(sprintf(
                    '  - %s: %d units, %s value',
                    $locationName,
                    $snapshot->total_quantity,
                    number_format($snapshot->total_value_minor / 100, 2)
                ));
            }
        } else {
            $snapshot = $valuationService->createSnapshot($method, $locationId);

            $locationName = $snapshot->location_id
                ? $snapshot->location->name ?? 'Unknown'
                : 'All Locations';

            $this->info('Snapshot created successfully!');
            $this->table(
                ['Location', 'SKUs', 'Quantity', 'Value', 'Avg Cost', 'Variance'],
                [[
                    $locationName,
                    $snapshot->sku_count,
                    number_format($snapshot->total_quantity),
                    number_format($snapshot->total_value_minor / 100, 2),
                    number_format($snapshot->average_unit_cost_minor / 100, 2),
                    $snapshot->variance_from_previous_minor !== null
                        ? number_format($snapshot->variance_from_previous_minor / 100, 2)
                        : 'N/A',
                ]]
            );
        }

        return self::SUCCESS;
    }
}
