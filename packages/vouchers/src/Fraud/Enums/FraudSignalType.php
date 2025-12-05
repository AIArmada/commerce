<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud\Enums;

/**
 * Types of fraud signals that can be detected.
 */
enum FraudSignalType: string
{
    // Velocity signals - detecting abnormally fast or frequent usage
    case HighRedemptionVelocity = 'high_redemption_velocity';
    case MultipleAccountsAttempt = 'multiple_accounts_attempt';
    case RapidCodeAttempts = 'rapid_code_attempts';
    case BurstRedemptions = 'burst_redemptions';

    // Pattern signals - detecting unusual patterns in usage
    case UnusualTimePattern = 'unusual_time_pattern';
    case GeoAnomalyDetected = 'geo_anomaly_detected';
    case DeviceFingerprintMismatch = 'device_fingerprint_mismatch';
    case IpAddressAnomaly = 'ip_address_anomaly';
    case SessionAnomaly = 'session_anomaly';

    // Behavioral signals - detecting suspicious user behavior
    case OnlyDiscountedPurchases = 'only_discounted_purchases';
    case HighRefundRate = 'high_refund_rate';
    case CartManipulation = 'cart_manipulation';
    case SuspiciousCheckoutPattern = 'suspicious_checkout_pattern';
    case AbnormalCartValue = 'abnormal_cart_value';

    // Code abuse signals - detecting code-specific fraud
    case CodeSharingDetected = 'code_sharing_detected';
    case LeakedCodeUsage = 'leaked_code_usage';
    case SequentialCodeAttempts = 'sequential_code_attempts';
    case InvalidCodeBruteforce = 'invalid_code_bruteforce';
    case ExpiredCodeAbuse = 'expired_code_abuse';

    /**
     * Get a human-readable label for the signal type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::HighRedemptionVelocity => 'High Redemption Velocity',
            self::MultipleAccountsAttempt => 'Multiple Accounts Attempt',
            self::RapidCodeAttempts => 'Rapid Code Attempts',
            self::BurstRedemptions => 'Burst Redemptions',
            self::UnusualTimePattern => 'Unusual Time Pattern',
            self::GeoAnomalyDetected => 'Geographic Anomaly',
            self::DeviceFingerprintMismatch => 'Device Fingerprint Mismatch',
            self::IpAddressAnomaly => 'IP Address Anomaly',
            self::SessionAnomaly => 'Session Anomaly',
            self::OnlyDiscountedPurchases => 'Only Discounted Purchases',
            self::HighRefundRate => 'High Refund Rate',
            self::CartManipulation => 'Cart Manipulation',
            self::SuspiciousCheckoutPattern => 'Suspicious Checkout Pattern',
            self::AbnormalCartValue => 'Abnormal Cart Value',
            self::CodeSharingDetected => 'Code Sharing Detected',
            self::LeakedCodeUsage => 'Leaked Code Usage',
            self::SequentialCodeAttempts => 'Sequential Code Attempts',
            self::InvalidCodeBruteforce => 'Invalid Code Bruteforce',
            self::ExpiredCodeAbuse => 'Expired Code Abuse',
        };
    }

    /**
     * Get a description of the signal type.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::HighRedemptionVelocity => 'Voucher is being redeemed at an unusually high rate',
            self::MultipleAccountsAttempt => 'Same voucher used from multiple accounts in short time',
            self::RapidCodeAttempts => 'Multiple voucher code attempts in rapid succession',
            self::BurstRedemptions => 'Sudden spike in redemptions for a single voucher',
            self::UnusualTimePattern => 'Redemption at unusual hours or patterns',
            self::GeoAnomalyDetected => 'Redemption from unexpected geographic location',
            self::DeviceFingerprintMismatch => 'Device characteristics do not match user profile',
            self::IpAddressAnomaly => 'Suspicious IP address or proxy detected',
            self::SessionAnomaly => 'Session behavior inconsistent with normal usage',
            self::OnlyDiscountedPurchases => 'User only makes purchases with discounts',
            self::HighRefundRate => 'User has abnormally high refund rate',
            self::CartManipulation => 'Cart modified to maximize discount value',
            self::SuspiciousCheckoutPattern => 'Checkout behavior suggests automated activity',
            self::AbnormalCartValue => 'Cart value unusual for the discount being applied',
            self::CodeSharingDetected => 'Unique code shared across multiple users',
            self::LeakedCodeUsage => 'Code appears to have been publicly shared',
            self::SequentialCodeAttempts => 'Attempts using sequential or patterned codes',
            self::InvalidCodeBruteforce => 'Multiple invalid code attempts suggesting bruteforce',
            self::ExpiredCodeAbuse => 'Repeated attempts to use expired vouchers',
        };
    }

    /**
     * Get the category of this signal.
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::HighRedemptionVelocity,
            self::MultipleAccountsAttempt,
            self::RapidCodeAttempts,
            self::BurstRedemptions => 'velocity',

            self::UnusualTimePattern,
            self::GeoAnomalyDetected,
            self::DeviceFingerprintMismatch,
            self::IpAddressAnomaly,
            self::SessionAnomaly => 'pattern',

            self::OnlyDiscountedPurchases,
            self::HighRefundRate,
            self::CartManipulation,
            self::SuspiciousCheckoutPattern,
            self::AbnormalCartValue => 'behavioral',

            self::CodeSharingDetected,
            self::LeakedCodeUsage,
            self::SequentialCodeAttempts,
            self::InvalidCodeBruteforce,
            self::ExpiredCodeAbuse => 'code_abuse',
        };
    }

    /**
     * Get the default severity score for this signal (0-100).
     */
    public function getDefaultSeverity(): int
    {
        return match ($this) {
            // Critical signals (60-80)
            self::LeakedCodeUsage => 80,
            self::CodeSharingDetected => 75,
            self::InvalidCodeBruteforce => 70,
            self::HighRedemptionVelocity => 65,
            self::BurstRedemptions => 60,

            // High severity (40-59)
            self::MultipleAccountsAttempt => 55,
            self::SequentialCodeAttempts => 50,
            self::GeoAnomalyDetected => 45,
            self::IpAddressAnomaly => 45,
            self::DeviceFingerprintMismatch => 40,

            // Medium severity (20-39)
            self::RapidCodeAttempts => 35,
            self::CartManipulation => 35,
            self::SuspiciousCheckoutPattern => 30,
            self::SessionAnomaly => 30,
            self::HighRefundRate => 25,
            self::ExpiredCodeAbuse => 25,

            // Low severity (0-19)
            self::UnusualTimePattern => 15,
            self::OnlyDiscountedPurchases => 15,
            self::AbnormalCartValue => 10,
        };
    }

    /**
     * Get all signals in a specific category.
     *
     * @return array<FraudSignalType>
     */
    public static function byCategory(string $category): array
    {
        return array_filter(
            self::cases(),
            fn (self $signal): bool => $signal->getCategory() === $category
        );
    }

    /**
     * Get all velocity-related signals.
     *
     * @return array<FraudSignalType>
     */
    public static function velocitySignals(): array
    {
        return self::byCategory('velocity');
    }

    /**
     * Get all pattern-related signals.
     *
     * @return array<FraudSignalType>
     */
    public static function patternSignals(): array
    {
        return self::byCategory('pattern');
    }

    /**
     * Get all behavioral signals.
     *
     * @return array<FraudSignalType>
     */
    public static function behavioralSignals(): array
    {
        return self::byCategory('behavioral');
    }

    /**
     * Get all code abuse signals.
     *
     * @return array<FraudSignalType>
     */
    public static function codeAbuseSignals(): array
    {
        return self::byCategory('code_abuse');
    }
}
