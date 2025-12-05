<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud\Detectors;

use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\FraudSignal;
use Illuminate\Database\Eloquent\Model;

/**
 * Detects pattern-based fraud signals.
 *
 * Monitors for anomalies in:
 * - Time patterns (unusual hours, sudden activity changes)
 * - Geographic patterns (impossible travel, suspicious locations)
 * - Device fingerprints (mismatches, known bad devices)
 * - IP addresses (proxies, data centers, blacklisted)
 * - Sessions (hijacking indicators, inconsistencies)
 */
class PatternDetector extends AbstractFraudDetector
{
    /**
     * Hours considered unusual for transactions (0-23).
     *
     * @var array<int>
     */
    protected array $unusualHours = [0, 1, 2, 3, 4, 5];

    /**
     * Known suspicious IP patterns (data centers, proxies).
     *
     * @var array<string>
     */
    protected array $suspiciousIpPatterns = [];

    /**
     * Known proxy/VPN detection enabled.
     */
    protected bool $proxyDetectionEnabled = true;

    /**
     * Maximum travel speed in km/h for geo validation.
     */
    protected float $maxTravelSpeedKmh = 1000; // ~airplane speed

    public function getName(): string
    {
        return 'pattern';
    }

    public function getCategory(): string
    {
        return 'pattern';
    }

    /**
     * Set unusual hours for detection.
     *
     * @param  array<int>  $hours
     */
    public function setUnusualHours(array $hours): static
    {
        $this->unusualHours = $hours;

        return $this;
    }

    /**
     * Set suspicious IP patterns.
     *
     * @param  array<string>  $patterns
     */
    public function setSuspiciousIpPatterns(array $patterns): static
    {
        $this->suspiciousIpPatterns = $patterns;

        return $this;
    }

    protected function analyze(
        string $code,
        object $cart,
        ?Model $user,
        array $context,
    ): void {
        $this->checkUnusualTimePattern($context);
        $this->checkGeoAnomaly($user, $context);
        $this->checkDeviceFingerprintMismatch($user, $context);
        $this->checkIpAddressAnomaly($context);
        $this->checkSessionAnomaly($user, $context);
    }

    /**
     * Check for unusual time patterns.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkUnusualTimePattern(array $context): void
    {
        $hour = (int) date('G'); // Current hour 0-23

        if (in_array($hour, $this->unusualHours, true)) {
            $this->addSignal(FraudSignal::create(
                type: FraudSignalType::UnusualTimePattern,
                message: "Transaction at unusual hour: {$hour}:00",
                metadata: [
                    'hour' => $hour,
                    'unusual_hours' => $this->unusualHours,
                ],
            ));
        }

        // Check for first-time user transacting at unusual hour
        $isFirstTransaction = $this->getContextValue($context, 'is_first_transaction', false);
        if ($isFirstTransaction && in_array($hour, $this->unusualHours, true)) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::UnusualTimePattern,
                score: 35,
                message: 'First-time user transacting at unusual hour',
                metadata: [
                    'hour' => $hour,
                    'is_first_transaction' => true,
                ],
            ));
        }
    }

    /**
     * Check for geographic anomalies (impossible travel, etc).
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkGeoAnomaly(?Model $user, array $context): void
    {
        $currentLocation = $this->getContextValue($context, 'geo_location');
        $previousLocation = $this->getContextValue($context, 'previous_geo_location');
        $timeSinceLast = $this->getContextValue($context, 'time_since_last_transaction_seconds');

        if ($currentLocation === null || $previousLocation === null || $timeSinceLast === null) {
            return;
        }

        // Calculate distance and check for impossible travel
        $distance = $this->calculateDistance(
            (float) ($previousLocation['lat'] ?? 0),
            (float) ($previousLocation['lng'] ?? 0),
            (float) ($currentLocation['lat'] ?? 0),
            (float) ($currentLocation['lng'] ?? 0),
        );

        if ($distance === 0.0) {
            return;
        }

        $hoursElapsed = $timeSinceLast / 3600;
        if ($hoursElapsed > 0) {
            $speedKmh = $distance / $hoursElapsed;

            if ($speedKmh > $this->maxTravelSpeedKmh) {
                $this->addSignal(FraudSignal::withScore(
                    type: FraudSignalType::GeoAnomalyDetected,
                    score: min(80, 50 + ($speedKmh / $this->maxTravelSpeedKmh) * 20),
                    message: sprintf(
                        'Impossible travel detected: %.0f km in %.1f hours (%.0f km/h)',
                        $distance,
                        $hoursElapsed,
                        $speedKmh
                    ),
                    metadata: [
                        'distance_km' => $distance,
                        'hours_elapsed' => $hoursElapsed,
                        'speed_kmh' => $speedKmh,
                        'max_speed_kmh' => $this->maxTravelSpeedKmh,
                        'current_location' => $currentLocation,
                        'previous_location' => $previousLocation,
                    ],
                ));
            }
        }
    }

    /**
     * Check for device fingerprint mismatches.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkDeviceFingerprintMismatch(?Model $user, array $context): void
    {
        $currentFingerprint = $this->getContextValue($context, 'device_fingerprint');
        $knownFingerprints = $this->getContextValue($context, 'known_device_fingerprints', []);

        if ($currentFingerprint === null || empty($knownFingerprints)) {
            return;
        }

        if (!in_array($currentFingerprint, $knownFingerprints, true)) {
            $this->addSignal(FraudSignal::create(
                type: FraudSignalType::DeviceFingerprintMismatch,
                message: 'Unknown device fingerprint detected',
                metadata: [
                    'current_fingerprint' => substr((string) $currentFingerprint, 0, 16) . '...',
                    'known_fingerprints_count' => count($knownFingerprints),
                ],
            ));
        }
    }

    /**
     * Check for IP address anomalies.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkIpAddressAnomaly(array $context): void
    {
        $ipAddress = $this->getContextValue($context, 'ip_address');

        if ($ipAddress === null) {
            return;
        }

        // Check against suspicious patterns
        foreach ($this->suspiciousIpPatterns as $pattern) {
            if (str_starts_with((string) $ipAddress, $pattern)) {
                $this->addSignal(FraudSignal::create(
                    type: FraudSignalType::IpAddressAnomaly,
                    message: 'IP address matches known suspicious pattern',
                    metadata: [
                        'ip_address' => $ipAddress,
                        'matched_pattern' => $pattern,
                    ],
                ));

                return;
            }
        }

        // Check for proxy/VPN if enabled
        $isProxy = $this->getContextValue($context, 'is_proxy', false);
        $isVpn = $this->getContextValue($context, 'is_vpn', false);
        $isDataCenter = $this->getContextValue($context, 'is_data_center', false);

        if ($this->proxyDetectionEnabled && ($isProxy || $isVpn || $isDataCenter)) {
            $type = match (true) {
                $isProxy => 'proxy',
                $isVpn => 'VPN',
                $isDataCenter => 'data center',
                default => 'suspicious',
            };

            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::IpAddressAnomaly,
                score: 30,
                message: "IP address detected as {$type}",
                metadata: [
                    'ip_address' => $ipAddress,
                    'is_proxy' => $isProxy,
                    'is_vpn' => $isVpn,
                    'is_data_center' => $isDataCenter,
                ],
            ));
        }
    }

    /**
     * Check for session anomalies.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkSessionAnomaly(?Model $user, array $context): void
    {
        $sessionId = $this->getContextValue($context, 'session_id');
        $previousSessionId = $this->getContextValue($context, 'previous_session_id');

        if ($sessionId === null || $previousSessionId === null) {
            return;
        }

        // Check for session ID change without re-authentication
        $wasReauthenticated = $this->getContextValue($context, 'was_reauthenticated', false);

        if ($sessionId !== $previousSessionId && !$wasReauthenticated) {
            $this->addSignal(FraudSignal::create(
                type: FraudSignalType::SessionAnomaly,
                message: 'Session ID changed without re-authentication',
                metadata: [
                    'session_changed' => true,
                    'was_reauthenticated' => $wasReauthenticated,
                ],
            ));
        }

        // Check for concurrent sessions if provided
        $concurrentSessions = $this->getContextValue($context, 'concurrent_session_count', 0);
        if ($concurrentSessions > 3) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::SessionAnomaly,
                score: 25 + min(25, ($concurrentSessions - 3) * 5),
                message: "Unusually high concurrent sessions: {$concurrentSessions}",
                metadata: [
                    'concurrent_sessions' => $concurrentSessions,
                ],
            ));
        }
    }

    /**
     * Calculate distance between two points using Haversine formula.
     *
     * @return float Distance in kilometers
     */
    protected function calculateDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $earthRadius = 6371; // km

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
