<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud\Detectors;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\FraudSignal;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherRedemption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Detects code abuse patterns.
 *
 * Monitors for:
 * - Code sharing (same code from different IPs/devices)
 * - Leaked code usage (codes appearing in known breach patterns)
 * - Sequential code attempts (systematic guessing)
 * - Brute force attempts (rapid invalid code tries)
 * - Expired code abuse (repeated expired code attempts)
 */
class CodeAbuseDetector extends AbstractFraudDetector
{
    /**
     * Maximum unique IPs per code before triggering sharing detection.
     */
    protected int $maxUniqueIpsPerCode = 5;

    /**
     * Maximum sequential invalid attempts before brute force detection.
     */
    protected int $maxSequentialInvalidAttempts = 5;

    /**
     * Maximum expired code attempts before abuse detection.
     */
    protected int $maxExpiredCodeAttempts = 3;

    /**
     * Time window for analysis in hours.
     */
    protected int $analysisWindowHours = 24;

    /**
     * Known leaked code patterns (for demonstration).
     *
     * @var array<string>
     */
    protected array $knownLeakedPatterns = [];

    public function getName(): string
    {
        return 'code_abuse';
    }

    public function getCategory(): string
    {
        return 'code_abuse';
    }

    /**
     * Configure the detector.
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): static
    {
        if (isset($config['max_unique_ips_per_code'])) {
            $this->maxUniqueIpsPerCode = (int) $config['max_unique_ips_per_code'];
        }
        if (isset($config['max_sequential_invalid_attempts'])) {
            $this->maxSequentialInvalidAttempts = (int) $config['max_sequential_invalid_attempts'];
        }
        if (isset($config['max_expired_code_attempts'])) {
            $this->maxExpiredCodeAttempts = (int) $config['max_expired_code_attempts'];
        }
        if (isset($config['analysis_window_hours'])) {
            $this->analysisWindowHours = (int) $config['analysis_window_hours'];
        }
        if (isset($config['known_leaked_patterns'])) {
            $this->knownLeakedPatterns = (array) $config['known_leaked_patterns'];
        }

        return $this;
    }

    protected function analyze(
        string $code,
        object $cart,
        ?Model $user,
        array $context,
    ): void {
        $this->checkCodeSharing($code, $context);
        $this->checkLeakedCodeUsage($code, $context);
        $this->checkSequentialCodeAttempts($user, $context);
        $this->checkInvalidCodeBruteforce($user, $context);
        $this->checkExpiredCodeAbuse($user, $context);
    }

    /**
     * Check for code sharing (same code from multiple IPs/devices).
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkCodeSharing(string $code, array $context): void
    {
        $uniqueIps = $this->getUniqueIpsForCode($code);

        if ($uniqueIps >= $this->maxUniqueIpsPerCode) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::CodeSharingDetected,
                score: min(70, 35 + ($uniqueIps - $this->maxUniqueIpsPerCode) * 7),
                message: "Code used from {$uniqueIps} unique IP addresses",
                metadata: [
                    'unique_ips' => $uniqueIps,
                    'threshold' => $this->maxUniqueIpsPerCode,
                    'code' => $this->maskCode($code),
                ],
            ));
        }

        // Check for unique devices
        $uniqueDevices = $this->getUniqueDevicesForCode($code);
        if ($uniqueDevices >= $this->maxUniqueIpsPerCode) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::CodeSharingDetected,
                score: min(60, 30 + ($uniqueDevices - $this->maxUniqueIpsPerCode) * 6),
                message: "Code used from {$uniqueDevices} unique devices",
                metadata: [
                    'unique_devices' => $uniqueDevices,
                    'threshold' => $this->maxUniqueIpsPerCode,
                    'code' => $this->maskCode($code),
                ],
            ));
        }
    }

    /**
     * Check for usage of known leaked codes.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkLeakedCodeUsage(string $code, array $context): void
    {
        foreach ($this->knownLeakedPatterns as $pattern) {
            if (Str::contains(mb_strtoupper($code), mb_strtoupper($pattern))) {
                $this->addSignal(FraudSignal::create(
                    type: FraudSignalType::LeakedCodeUsage,
                    message: 'Code matches known leaked pattern',
                    metadata: [
                        'code' => $this->maskCode($code),
                        'pattern_matched' => true,
                    ],
                ));

                return;
            }
        }

        // Check if code was flagged as leaked
        $isKnownLeaked = $this->getContextValue($context, 'is_known_leaked_code', false);
        if ($isKnownLeaked) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::LeakedCodeUsage,
                score: 80,
                message: 'Code is known to be leaked or compromised',
                metadata: [
                    'code' => $this->maskCode($code),
                    'source' => $this->getContextValue($context, 'leaked_code_source', 'unknown'),
                ],
            ));
        }
    }

    /**
     * Check for sequential code attempts (systematic guessing).
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkSequentialCodeAttempts(?Model $user, array $context): void
    {
        $recentAttempts = $this->getContextValue($context, 'recent_code_attempts', []);

        if (count($recentAttempts) < 3) {
            return;
        }

        // Check for sequential patterns (e.g., CODE1, CODE2, CODE3)
        $sequentialCount = $this->countSequentialPatterns($recentAttempts);

        if ($sequentialCount >= 3) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::SequentialCodeAttempts,
                score: min(80, 40 + $sequentialCount * 10),
                message: "Sequential code pattern detected: {$sequentialCount} attempts",
                metadata: [
                    'sequential_count' => $sequentialCount,
                    'pattern' => 'numeric_sequence',
                ],
            ));
        }

        // Check for alphabetic sequences
        $alphaSequentialCount = $this->countAlphaSequentialPatterns($recentAttempts);

        if ($alphaSequentialCount >= 3) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::SequentialCodeAttempts,
                score: min(70, 35 + $alphaSequentialCount * 8),
                message: "Alphabetic sequential pattern detected: {$alphaSequentialCount} attempts",
                metadata: [
                    'sequential_count' => $alphaSequentialCount,
                    'pattern' => 'alphabetic_sequence',
                ],
            ));
        }
    }

    /**
     * Check for brute force attempts (rapid invalid code tries).
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkInvalidCodeBruteforce(?Model $user, array $context): void
    {
        $invalidAttempts = $this->getContextValue($context, 'recent_invalid_attempts', 0);
        $attemptTimeWindow = $this->getContextValue($context, 'invalid_attempt_window_minutes', 5);

        if ($invalidAttempts >= $this->maxSequentialInvalidAttempts) {
            $severity = min(80, 40 + ($invalidAttempts - $this->maxSequentialInvalidAttempts) * 8);

            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::InvalidCodeBruteforce,
                score: $severity,
                message: "{$invalidAttempts} invalid code attempts in {$attemptTimeWindow} minutes",
                metadata: [
                    'invalid_attempts' => $invalidAttempts,
                    'threshold' => $this->maxSequentialInvalidAttempts,
                    'time_window_minutes' => $attemptTimeWindow,
                ],
            ));
        }
    }

    /**
     * Check for expired code abuse.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkExpiredCodeAbuse(?Model $user, array $context): void
    {
        $expiredAttempts = $this->getContextValue($context, 'expired_code_attempts', 0);

        if ($expiredAttempts >= $this->maxExpiredCodeAttempts) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::ExpiredCodeAbuse,
                score: min(50, 25 + ($expiredAttempts - $this->maxExpiredCodeAttempts) * 8),
                message: "Repeated use of expired codes: {$expiredAttempts} attempts",
                metadata: [
                    'expired_attempts' => $expiredAttempts,
                    'threshold' => $this->maxExpiredCodeAttempts,
                ],
            ));
        }

        // Check for recently-expired code attempts (timing attack)
        $recentlyExpiredAttempts = $this->getContextValue($context, 'recently_expired_attempts', 0);
        if ($recentlyExpiredAttempts >= 2) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::ExpiredCodeAbuse,
                score: 35,
                message: 'Multiple attempts with recently-expired codes',
                metadata: [
                    'recently_expired_attempts' => $recentlyExpiredAttempts,
                ],
            ));
        }
    }

    /**
     * Get unique IPs that have used a code.
     */
    protected function getUniqueIpsForCode(string $code): int
    {
        if (! class_exists(VoucherRedemption::class) || ! class_exists(Voucher::class)) {
            return 0;
        }

        $since = Carbon::now()->subHours($this->analysisWindowHours);

        return VoucherRedemption::query()
            ->whereHas('voucher', function ($query) use ($code): void {
                $query->where('code', $code);
                $this->scopeVoucherOwner($query);
            })
            ->where('created_at', '>=', $since)
            ->whereNotNull('ip_address')
            ->distinct('ip_address')
            ->count('ip_address');
    }

    /**
     * Get unique devices that have used a code.
     */
    protected function getUniqueDevicesForCode(string $code): int
    {
        if (! class_exists(VoucherRedemption::class) || ! class_exists(Voucher::class)) {
            return 0;
        }

        $since = Carbon::now()->subHours($this->analysisWindowHours);

        return VoucherRedemption::query()
            ->whereHas('voucher', function ($query) use ($code): void {
                $query->where('code', $code);
                $this->scopeVoucherOwner($query);
            })
            ->where('created_at', '>=', $since)
            ->whereNotNull('device_fingerprint')
            ->distinct('device_fingerprint')
            ->count('device_fingerprint');
    }

    private function scopeVoucherOwner($query): void
    {
        if (! config('vouchers.owner.enabled', false)) {
            return;
        }

        if (! app()->bound(OwnerResolverInterface::class)) {
            return;
        }

        /** @var OwnerResolverInterface $resolver */
        $resolver = app(OwnerResolverInterface::class);
        $owner = $resolver->resolve();
        $includeGlobal = (bool) config('vouchers.owner.include_global', true);

        if (method_exists($query, 'forOwner')) {
            $query->forOwner($owner, $includeGlobal);
        }
    }

    /**
     * Count sequential numeric patterns in code attempts.
     *
     * @param  array<string>  $attempts
     */
    protected function countSequentialPatterns(array $attempts): int
    {
        $sequential = 0;

        foreach ($attempts as $i => $code) {
            if (! isset($attempts[$i + 1])) {
                continue;
            }

            $nextCode = $attempts[$i + 1];

            // Extract numeric suffixes
            preg_match('/(\d+)$/', $code, $currentMatch);
            preg_match('/(\d+)$/', $nextCode, $nextMatch);

            if (! empty($currentMatch) && ! empty($nextMatch)) {
                $currentNum = (int) $currentMatch[1];
                $nextNum = (int) $nextMatch[1];

                if ($nextNum === $currentNum + 1) {
                    $sequential++;
                }
            }
        }

        return $sequential;
    }

    /**
     * Count alphabetic sequential patterns in code attempts.
     *
     * @param  array<string>  $attempts
     */
    protected function countAlphaSequentialPatterns(array $attempts): int
    {
        $sequential = 0;

        foreach ($attempts as $i => $code) {
            if (! isset($attempts[$i + 1])) {
                continue;
            }

            $nextCode = $attempts[$i + 1];

            // Check if last char is sequential
            $lastCurrent = mb_strtoupper(mb_substr($code, -1));
            $lastNext = mb_strtoupper(mb_substr($nextCode, -1));

            if (ctype_alpha($lastCurrent) && ctype_alpha($lastNext)) {
                if (ord($lastNext) === ord($lastCurrent) + 1) {
                    $sequential++;
                }
            }
        }

        return $sequential;
    }

    /**
     * Mask a code for logging (show first 3 and last 2 chars).
     */
    protected function maskCode(string $code): string
    {
        $length = mb_strlen($code);

        if ($length <= 5) {
            return str_repeat('*', $length);
        }

        return mb_substr($code, 0, 3) . str_repeat('*', $length - 5) . mb_substr($code, -2);
    }
}
