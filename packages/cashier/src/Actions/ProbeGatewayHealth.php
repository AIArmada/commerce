<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\GatewayHealthProbe;
use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ProbeGatewayHealth
{
    public function __construct(private GatewayHealthProbe $probe) {}

    public function handle(string $gateway, bool $force = false): string
    {
        if (! in_array($gateway, ['stripe', 'chip'], true)) {
            return 'unknown';
        }

        try {
            $owner = OwnerContext::resolve();
            $key = 'cashier.gateway_health.' . $gateway . '.' . $this->probe->fingerprint($gateway);

            if ($force) {
                OwnerCache::forget($owner, $key);
            }

            if (! $force && OwnerCache::get($owner, $key) === 'healthy') {
                return 'healthy';
            }

            $status = $this->probe->probe($gateway);

            if ($status === 'healthy') {
                OwnerCache::put($owner, $key, $status, 60);
            }

            return $status;
        } catch (Throwable $e) {
            Log::warning('Cashier gateway health probe failed.', [
                'gateway' => $gateway,
                'exception' => $e::class,
            ]);

            return 'error';
        }
    }
}
