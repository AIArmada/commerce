<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntNetworkException;
use AIArmada\Jnt\Services\JntExpressService;
use Exception;
use Illuminate\Console\Command;

class OrderTrackCommand extends Command
{
    protected $signature = 'jnt:order:track {order-id : Order ID or tracking number to track}';

    protected $description = 'Track a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        $orderId = $this->argument('order-id');

        try {
            $this->line('Tracking order: ' . $orderId);

            $tracking = $jnt->trackParcel($orderId);

            if ($tracking->details->count() === 0) {
                $this->warn('No tracking information found for this order.');

                return self::SUCCESS;
            }

            $this->info('✓ Tracking Information Found');

            // Display tracking details
            $details = [];
            foreach ($tracking->details->toCollection() as $detail) {
                $details[] = [
                    $detail->scanTime ?? 'N/A',
                    $detail->scanType ?? 'N/A',
                    $detail->description ?? 'No description',
                ];
            }

            $this->table(['Time', 'Status', 'Description'], $details);

            return self::SUCCESS;
        } catch (JntNetworkException $e) {
            $this->error('Network Error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (JntApiException $e) {
            $this->error('API Error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
