<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Services\JntExpressService;
use Exception;
use Illuminate\Console\Command;

class OrderCancelCommand extends Command
{
    protected $signature = 'jnt:order:cancel {order-id : Order ID to cancel} {--reason= : Cancellation reason}';

    protected $description = 'Cancel a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        $orderId = $this->argument('order-id');
        $reasonInput = $this->option('reason');

        // If no reason provided, ask for it
        if (! $reasonInput) {
            $reasons = collect(CancellationReason::cases())
                ->map(fn (CancellationReason $reason): string => $reason->value)
                ->all();

            $reasonInput = $this->choice('Select cancellation reason', $reasons);
        }

        // Try to match to enum, otherwise use as string
        $reason = CancellationReason::tryFrom($reasonInput) ?? $reasonInput;

        if (! $this->confirm(sprintf('Cancel order %s?', $orderId), true)) {
            $this->info('Cancellation aborted.');

            return self::SUCCESS;
        }

        try {
            $jnt->cancelOrder($orderId, $reason);

            $this->info('✓ Order cancelled successfully!');

            return self::SUCCESS;
        } catch (JntApiException $e) {
            $this->error('API Error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
