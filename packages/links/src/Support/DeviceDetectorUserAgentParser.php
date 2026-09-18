<?php

declare(strict_types=1);

namespace AIArmada\Links\Support;

use AIArmada\Links\Contracts\UserAgentParserInterface;
use DeviceDetector\DeviceDetector;

final class DeviceDetectorUserAgentParser implements UserAgentParserInterface
{
    public function parse(?string $userAgent): array
    {
        if (! config('links.features.tracking.user_agent.enabled', true)) {
            return $this->emptyResult();
        }

        $userAgent = mb_trim((string) $userAgent);

        if ($userAgent === '') {
            return $this->emptyResult();
        }

        $detector = new DeviceDetector($userAgent);
        $detector->discardBotInformation();
        $detector->parse();

        if ($detector->isBot()) {
            $result = $this->emptyResult();
            $result['is_bot'] = true;

            return $result;
        }

        /** @var array{name?: string, version?: string}|null $client */
        $client = $detector->getClient();

        /** @var array{name?: string, version?: string}|null $os */
        $os = $detector->getOs();

        $brand = $detector->getBrandName();
        $model = $detector->getModel();
        $deviceType = $this->normalizeDeviceType($detector->getDeviceName());

        return [
            'device_type' => $deviceType !== '' ? $deviceType : null,
            'device_brand' => ($brand !== '' && $brand !== 'Unknown') ? $brand : null,
            'device_model' => ($model !== '' && $model !== 'Unknown') ? $model : null,
            'browser' => $client['name'] ?? null,
            'browser_version' => isset($client['version']) && $client['version'] !== '' ? $client['version'] : null,
            'os' => $os['name'] ?? null,
            'os_version' => isset($os['version']) && $os['version'] !== '' ? $os['version'] : null,
            'is_bot' => false,
        ];
    }

    /**
     * @return array{device_type: null, device_brand: null, device_model: null, browser: null, browser_version: null, os: null, os_version: null, is_bot: bool}
     */
    private function emptyResult(): array
    {
        return [
            'device_type' => null,
            'device_brand' => null,
            'device_model' => null,
            'browser' => null,
            'browser_version' => null,
            'os' => null,
            'os_version' => null,
            'is_bot' => false,
        ];
    }

    private function normalizeDeviceType(string $deviceName): string
    {
        return match (mb_strtolower($deviceName)) {
            'smartphone' => 'mobile',
            'feature phone' => 'mobile',
            'tablet' => 'tablet',
            'phablet' => 'tablet',
            'desktop' => 'desktop',
            'tv' => 'tv',
            'smart display' => 'tv',
            'console' => 'console',
            'portable media player' => 'mobile',
            default => $deviceName,
        };
    }
}
