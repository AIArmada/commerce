<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Drivers;

use AIArmada\Jnt\Contracts\CarrierTransport;
use AIArmada\Jnt\Contracts\CarrierTransportFactory;
use AIArmada\Jnt\Exceptions\JntConfigurationException;

final class HttpCarrierTransportFactory implements CarrierTransportFactory
{
    public function create(array $config): CarrierTransport
    {
        $environment = (string) ($config['environment'] ?? 'testing');
        $normalized = match ($environment) {
            'production' => 'production',
            'testing', 'local', 'development' => 'testing',
            default => throw JntConfigurationException::invalidEnvironment($environment),
        };

        $baseUrl = $config['base_urls'][$normalized] ?? null;
        $account = $config['api_account'] ?? null;
        $key = $config['private_key'] ?? null;

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw JntConfigurationException::invalidValue('jnt.base_urls', 'Missing API base URL.');
        }

        if (! is_string($account) || trim($account) === '') {
            throw JntConfigurationException::missingApiAccount();
        }

        if (! is_string($key) || trim($key) === '') {
            throw JntConfigurationException::missingPrivateKey();
        }

        return new JntClient($baseUrl, $account, $key, $config);
    }
}
