<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Services\WebhookService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WebhookTestCommand extends Command
{
    protected $signature = 'jnt:webhook:test {--url= : Webhook URL to test}';

    protected $description = 'Test J&T Express webhook endpoint';

    public function handle(WebhookService $webhookService): int
    {
        $url = $this->option('url') ?: config('jnt.webhook.url', route('jnt.webhook'));

        $this->info('Testing webhook endpoint: ' . $url);

        // Generate sample webhook payload
        $samplePayload = [
            'digest' => '',
            'bizContent' => json_encode([
                'billCode' => 'TEST' . time(),
                'txlogisticId' => 'TEST-ORDER-' . time(),
                'details' => [
                    [
                        'scanTime' => now()->toIso8601String(),
                        'scanType' => 'collect',
                        'desc' => 'Package collected - Test webhook',
                    ],
                ],
            ]),
        ];

        // Generate signature
        $signature = $webhookService->generateSignature($samplePayload['bizContent']);
        $samplePayload['digest'] = $signature;

        try {
            $this->line('Sending test webhook...');

            $response = Http::post($url, $samplePayload);

            if ($response->successful()) {
                $this->info('✓ Webhook test successful!');
                $this->line('Status: ' . $response->status());
                $this->line('Response: ' . $response->body());
            } else {
                $this->error('✗ Webhook test failed!');
                $this->line('Status: ' . $response->status());
                $this->line('Response: ' . $response->body());

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error('Error: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
