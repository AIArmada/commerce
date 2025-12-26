<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud\Detectors;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\FraudSignal;
use AIArmada\Vouchers\Models\VoucherRedemption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Detects velocity-based fraud patterns.
 *
 * Monitors redemption rates and identifies suspicious patterns like:
 * - High redemption velocity (too many redemptions in short time)
 * - Multiple accounts attempting same code
 * - Rapid sequential code attempts
 * - Burst redemption patterns
 */
class VelocityDetector extends AbstractFraudDetector
{
    /**
     * Maximum redemptions per minute before triggering alert.
     */
    protected int $redemptionsPerMinuteThreshold = 5;

    /**
     * Maximum redemptions per hour before triggering alert.
     */
    protected int $redemptionsPerHourThreshold = 20;

    /**
     * Maximum unique accounts per code per hour.
     */
    protected int $maxAccountsPerCodePerHour = 3;

    /**
     * Maximum code attempts per minute.
     */
    protected int $codeAttemptsPerMinuteThreshold = 10;

    /**
     * Burst detection window in seconds.
     */
    protected int $burstWindowSeconds = 30;

    /**
     * Maximum redemptions in burst window.
     */
    protected int $burstThreshold = 3;

    public function getName(): string
    {
        return 'velocity';
    }

    public function getCategory(): string
    {
        return 'velocity';
    }

    /**
     * Configure thresholds for the detector.
     *
     * @param  array<string, int>  $thresholds
     */
    public function setThresholds(array $thresholds): static
    {
        if (isset($thresholds['redemptions_per_minute'])) {
            $this->redemptionsPerMinuteThreshold = $thresholds['redemptions_per_minute'];
        }
        if (isset($thresholds['redemptions_per_hour'])) {
            $this->redemptionsPerHourThreshold = $thresholds['redemptions_per_hour'];
        }
        if (isset($thresholds['max_accounts_per_code_per_hour'])) {
            $this->maxAccountsPerCodePerHour = $thresholds['max_accounts_per_code_per_hour'];
        }
        if (isset($thresholds['code_attempts_per_minute'])) {
            $this->codeAttemptsPerMinuteThreshold = $thresholds['code_attempts_per_minute'];
        }
        if (isset($thresholds['burst_window_seconds'])) {
            $this->burstWindowSeconds = $thresholds['burst_window_seconds'];
        }
        if (isset($thresholds['burst_threshold'])) {
            $this->burstThreshold = $thresholds['burst_threshold'];
        }

        return $this;
    }

    protected function analyze(
        string $code,
        object $cart,
        ?Model $user,
        array $context,
    ): void {
        $userId = $this->getUserId($user);
        $ipAddress = $this->getContextValue($context, 'ip_address');

        $this->checkHighRedemptionVelocity($code, $userId, $ipAddress);
        $this->checkMultipleAccountsAttempt($code);
        $this->checkRapidCodeAttempts($userId, $ipAddress);
        $this->checkBurstRedemptions($code, $userId, $ipAddress);
    }

    /**
     * Check for unusually high redemption velocity.
     */
    protected function checkHighRedemptionVelocity(
        string $code,
        ?string $userId,
        ?string $ipAddress,
    ): void {
        $now = Carbon::now();

        // Check redemptions per minute
        $redemptionsLastMinute = $this->getRedemptionCount($code, $now->copy()->subMinute());

        if ($redemptionsLastMinute >= $this->redemptionsPerMinuteThreshold) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::HighRedemptionVelocity,
                score: min(80, 40 + ($redemptionsLastMinute - $this->redemptionsPerMinuteThreshold) * 10),
                message: "High redemption velocity detected: {$redemptionsLastMinute} redemptions in the last minute",
                metadata: [
                    'count' => $redemptionsLastMinute,
                    'threshold' => $this->redemptionsPerMinuteThreshold,
                    'window' => '1 minute',
                ],
            ));

            return; // Skip hour check if minute already triggered
        }

        // Check redemptions per hour
        $redemptionsLastHour = $this->getRedemptionCount($code, $now->copy()->subHour());

        if ($redemptionsLastHour >= $this->redemptionsPerHourThreshold) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::HighRedemptionVelocity,
                score: min(60, 30 + ($redemptionsLastHour - $this->redemptionsPerHourThreshold) * 2),
                message: "Elevated redemption rate: {$redemptionsLastHour} redemptions in the last hour",
                metadata: [
                    'count' => $redemptionsLastHour,
                    'threshold' => $this->redemptionsPerHourThreshold,
                    'window' => '1 hour',
                ],
            ));
        }
    }

    /**
     * Check for multiple accounts attempting the same code.
     */
    protected function checkMultipleAccountsAttempt(string $code): void
    {
        $now = Carbon::now();
        $uniqueAccounts = $this->getUniqueAccountCount($code, $now->copy()->subHour());

        if ($uniqueAccounts >= $this->maxAccountsPerCodePerHour) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::MultipleAccountsAttempt,
                score: min(70, 40 + ($uniqueAccounts - $this->maxAccountsPerCodePerHour) * 10),
                message: "Code used by {$uniqueAccounts} different accounts in the last hour",
                metadata: [
                    'unique_accounts' => $uniqueAccounts,
                    'threshold' => $this->maxAccountsPerCodePerHour,
                    'window' => '1 hour',
                ],
            ));
        }
    }

    /**
     * Check for rapid sequential code attempts (brute force indicator).
     */
    protected function checkRapidCodeAttempts(?string $userId, ?string $ipAddress): void
    {
        if ($userId === null && $ipAddress === null) {
            return;
        }

        $now = Carbon::now();
        $attempts = $this->getCodeAttemptCount($userId, $ipAddress, $now->copy()->subMinute());

        if ($attempts >= $this->codeAttemptsPerMinuteThreshold) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::RapidCodeAttempts,
                score: min(80, 50 + ($attempts - $this->codeAttemptsPerMinuteThreshold) * 5),
                message: "{$attempts} code attempts in the last minute - possible brute force",
                metadata: [
                    'attempts' => $attempts,
                    'threshold' => $this->codeAttemptsPerMinuteThreshold,
                    'user_id' => $userId,
                    'ip_address' => $ipAddress,
                ],
            ));
        }
    }

    /**
     * Check for burst redemption patterns (many in very short window).
     */
    protected function checkBurstRedemptions(
        string $code,
        ?string $userId,
        ?string $ipAddress,
    ): void {
        $now = Carbon::now();
        $windowStart = $now->copy()->subSeconds($this->burstWindowSeconds);

        $burstCount = $this->getRedemptionCount($code, $windowStart);

        if ($burstCount >= $this->burstThreshold) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::BurstRedemptions,
                score: min(70, 35 + ($burstCount - $this->burstThreshold) * 15),
                message: "Burst pattern detected: {$burstCount} redemptions in {$this->burstWindowSeconds} seconds",
                metadata: [
                    'count' => $burstCount,
                    'threshold' => $this->burstThreshold,
                    'window_seconds' => $this->burstWindowSeconds,
                ],
            ));
        }
    }

    /**
     * Get the count of redemptions for a code since a given time.
     */
    protected function getRedemptionCount(string $code, Carbon $since): int
    {
        if (! class_exists(VoucherRedemption::class)) {
            return 0;
        }

        return VoucherRedemption::query()
            ->whereHas('voucher', function ($query) use ($code): void {
                $query->where('code', $code);
                $this->scopeVoucherOwner($query);
            })
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * Get the count of unique accounts that used a code since a given time.
     */
    protected function getUniqueAccountCount(string $code, Carbon $since): int
    {
        if (! class_exists(VoucherRedemption::class)) {
            return 0;
        }

        return VoucherRedemption::query()
            ->whereHas('voucher', function ($query) use ($code): void {
                $query->where('code', $code);
                $this->scopeVoucherOwner($query);
            })
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');
    }

    private function scopeVoucherOwner(Builder $query): void
    {
        if (! config('vouchers.owner.enabled', false)) {
            return;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('vouchers.owner.include_global', false);

        if (method_exists($query, 'forOwner')) {
            $query->forOwner($owner, $includeGlobal);
        }
    }

    /**
     * Get the count of code attempts by user or IP since a given time.
     *
     * This would typically query an attempts log table.
     * For now, we use redemptions as a proxy.
     */
    protected function getCodeAttemptCount(?string $userId, ?string $ipAddress, Carbon $since): int
    {
        if (! class_exists(VoucherRedemption::class)) {
            return 0;
        }

        $query = VoucherRedemption::query()
            ->where('created_at', '>=', $since);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } elseif ($ipAddress !== null) {
            $query->where('ip_address', $ipAddress);
        } else {
            return 0;
        }

        return $query->count();
    }
}
