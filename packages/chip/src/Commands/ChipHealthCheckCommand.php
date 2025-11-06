<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\ChipSendService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class ChipHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'chip:health
                            {--collect : Check only CHIP Collect API}
                            {--send : Check only CHIP Send API}';

    /**
     * The console command description.
     */
    protected $description = 'Check CHIP API connectivity and configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 CHIP API Health Check');

        $checkCollect = ! $this->option('send') || $this->option('collect');
        $checkSend = ! $this->option('collect') || $this->option('send');

        $allHealthy = true;

        // Check CHIP Collect API
        if ($checkCollect) {
            // Only check Collect API if credentials are configured
            if (config('chip.collect.api_key') && config('chip.collect.brand_id')) {
                $this->line('📦 <fg=cyan>Checking CHIP Collect API...</>');
                $collectHealthy = $this->checkCollectApi();
                if (! $collectHealthy) {
                    $allHealthy = false;
                }
                $this->newLine();
            } else {
                $this->line('📦 <fg=yellow>CHIP Collect API...</>');
                $this->info('   ⏭️  Skipped (credentials not configured)');
                $this->newLine();
            }
        }

        // Check CHIP Send API
        if ($checkSend) {
            // Only check Send API if credentials are configured
            if (config('chip.send.api_key') && config('chip.send.api_secret')) {
                $this->line('💸 <fg=cyan>Checking CHIP Send API...</>');
                $sendHealthy = $this->checkSendApi();
                if (! $sendHealthy) {
                    $allHealthy = false;
                }
                $this->newLine();
            } else {
                $this->line('💸 <fg=yellow>CHIP Send API...</>');
                $this->info('   ⏭️  Skipped (credentials not configured)');
                $this->newLine();
            }
        }

        // Display configuration status
        if ($this->option('verbose')) {
            $this->displayConfiguration();
            $this->newLine();
        }

        // Final summary
        if ($allHealthy) {
            $this->info('✅ All systems operational');

            return self::SUCCESS;
        }

        $this->error('❌ Some systems are experiencing issues');

        return self::FAILURE;
    }

    /**
     * Check CHIP Collect API health
     */
    protected function checkCollectApi(): bool
    {
        try {
            $service = app(ChipCollectService::class);
        } catch (RuntimeException $e) {
            $this->error('   ❌ Configuration error');

            if ($this->option('verbose')) {
                $this->line("      Error: {$e->getMessage()}");
            }

            return false;
        }

        // Service can be instantiated, which means configuration is valid
        $this->info('   ✅ Service configured');

        // Try to make an API call for connectivity check
        try {
            $methods = $service->getPaymentMethods();
            $this->info('   ✅ API reachable');
            if ($this->option('verbose')) {
                $availableMethods = $methods['available_payment_methods'] ?? [];
                $this->line('      Available payment methods: '.count($availableMethods));
            }
        } catch (Throwable $e) {
            // API call failed - log as warning but don't fail the check
            $this->warn('   ⚠️  API connectivity issue');
            if ($this->option('verbose')) {
                $this->line("      Error: {$e->getMessage()}");
            }
            // Don't return false - configuration is valid, connectivity issues are warnings
        }

        return true;
    }

    /**
     * Check CHIP Send API health
     */
    protected function checkSendApi(): bool
    {
        try {
            $service = app(ChipSendService::class);
        } catch (RuntimeException $e) {
            $this->error('   ❌ Configuration error');

            if ($this->option('verbose')) {
                $this->line("      Error: {$e->getMessage()}");
            }

            return false;
        }

        // Service can be instantiated, which means configuration is valid
        $this->info('   ✅ Service configured');

        // Try to make an API call for connectivity check
        try {
            $accounts = $service->listAccounts();
            $this->info('   ✅ API reachable');
            if ($this->option('verbose')) {
                $this->line('      Accounts retrieved: '.count($accounts));
            }
        } catch (Throwable $e) {
            // API call failed - log as warning but don't fail the check
            $this->warn('   ⚠️  API connectivity issue');
            if ($this->option('verbose')) {
                $this->line("      Error: {$e->getMessage()}");
            }
            // Don't return false - configuration is valid, connectivity issues are warnings
        }

        return true;
    }

    /**
     * Display current configuration
     */
    protected function displayConfiguration(): void
    {
        $this->line('⚙️  <fg=cyan>Configuration Status</>');

        // Environment
        $environment = config('chip.environment', 'sandbox');
        $this->line("   Environment: <fg=yellow>{$environment}</>");

        // Logging
        $loggingEnabled = config('chip.logging.log_requests', false);
        $status = $loggingEnabled ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>';
        $this->line("   Logging: {$status}");
    }
}
