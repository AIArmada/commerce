<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Console;

use Illuminate\Console\Command;

class WebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashier-chip:webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the CHIP webhook for handling subscription and payment events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('CHIP Webhook URL Configuration');

        $webhookUrl = url(config('cashier-chip.path', 'chip').'/webhook');

        $this->components->twoColumnDetail(
            'Webhook URL',
            $webhookUrl
        );

        $this->newLine();

        $this->components->bulletList([
            'Configure this URL in your CHIP dashboard under Webhooks',
            'Enable the following events: purchase.payment.success, purchase.payment.failed, purchase.completed',
            'Make sure your webhook secret is set in your .env file as CHIP_WEBHOOK_SECRET',
        ]);

        $this->newLine();

        $this->components->info('Required Environment Variables:');

        $this->components->twoColumnDetail('CHIP_WEBHOOK_SECRET', config('cashier-chip.webhooks.secret') ? '✓ Set' : '✗ Not set');
        $this->components->twoColumnDetail('CHIP_WEBHOOK_VERIFY', config('cashier-chip.webhooks.verify_signature', true) ? 'Enabled' : 'Disabled');

        return Command::SUCCESS;
    }
}
