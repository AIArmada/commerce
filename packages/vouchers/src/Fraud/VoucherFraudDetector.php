<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud;

use AIArmada\Vouchers\Fraud\Contracts\FraudDetectorInterface;
use AIArmada\Vouchers\Fraud\Detectors\BehavioralDetector;
use AIArmada\Vouchers\Fraud\Detectors\CodeAbuseDetector;
use AIArmada\Vouchers\Fraud\Detectors\PatternDetector;
use AIArmada\Vouchers\Fraud\Detectors\VelocityDetector;
use Illuminate\Database\Eloquent\Model;

/**
 * Main fraud detection orchestrator.
 *
 * Coordinates multiple fraud detectors and aggregates their results
 * into a comprehensive FraudAnalysis.
 */
final class VoucherFraudDetector
{
    /** @var array<string, FraudDetectorInterface> */
    private array $detectors = [];

    /**
     * Threshold for blocking redemption (0.0-1.0).
     */
    private float $blockThreshold = 0.8;

    /**
     * Whether to run detectors in parallel (for future async support).
     */
    private bool $parallelExecution = false;

    /**
     * Maximum combined score before automatic blocking.
     */
    private float $maxAllowedScore = 100.0;

    public function __construct()
    {
        $this->registerDefaultDetectors();
    }

    /**
     * Create a new instance with default configuration.
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Analyze a voucher redemption attempt for fraud.
     *
     * @param  string  $code  The voucher code being redeemed
     * @param  object  $cart  The cart associated with the redemption
     * @param  Model|null  $user  The user attempting the redemption
     * @param  array<string, mixed>  $context  Additional context (IP, device, etc.)
     */
    public function analyze(
        string $code,
        object $cart,
        ?Model $user = null,
        array $context = [],
    ): FraudAnalysis {
        $allSignals = [];
        $detectorResults = [];

        foreach ($this->detectors as $name => $detector) {
            if (! $detector->isEnabled()) {
                continue;
            }

            $result = $detector->detect($code, $cart, $user, $context);
            $detectorResults[$name] = $result;

            foreach ($result->signals as $signal) {
                $allSignals[] = $signal;
            }
        }

        return FraudAnalysis::fromSignals($allSignals, $this->blockThreshold);
    }

    /**
     * Quick check if a redemption should be blocked.
     *
     * @param  string  $code  The voucher code
     * @param  object  $cart  The cart
     * @param  Model|null  $user  The user
     * @param  array<string, mixed>  $context  Additional context
     */
    public function shouldBlock(
        string $code,
        object $cart,
        ?Model $user = null,
        array $context = [],
    ): bool {
        return $this->analyze($code, $cart, $user, $context)->shouldBlock;
    }

    /**
     * Get a quick risk assessment without full analysis.
     *
     * @param  string  $code  The voucher code
     * @param  object  $cart  The cart
     * @param  Model|null  $user  The user
     * @param  array<string, mixed>  $context  Additional context
     */
    public function getRiskLevel(
        string $code,
        object $cart,
        ?Model $user = null,
        array $context = [],
    ): Enums\FraudRiskLevel {
        return $this->analyze($code, $cart, $user, $context)->riskLevel;
    }

    /**
     * Register a fraud detector.
     */
    public function registerDetector(string $name, FraudDetectorInterface $detector): static
    {
        $this->detectors[$name] = $detector;

        return $this;
    }

    /**
     * Remove a detector.
     */
    public function removeDetector(string $name): static
    {
        unset($this->detectors[$name]);

        return $this;
    }

    /**
     * Get a registered detector.
     */
    public function getDetector(string $name): ?FraudDetectorInterface
    {
        return $this->detectors[$name] ?? null;
    }

    /**
     * Get all registered detectors.
     *
     * @return array<string, FraudDetectorInterface>
     */
    public function getDetectors(): array
    {
        return $this->detectors;
    }

    /**
     * Enable a specific detector.
     */
    public function enableDetector(string $name): static
    {
        if (isset($this->detectors[$name]) && method_exists($this->detectors[$name], 'setEnabled')) {
            $this->detectors[$name]->setEnabled(true);
        }

        return $this;
    }

    /**
     * Disable a specific detector.
     */
    public function disableDetector(string $name): static
    {
        if (isset($this->detectors[$name]) && method_exists($this->detectors[$name], 'setEnabled')) {
            $this->detectors[$name]->setEnabled(false);
        }

        return $this;
    }

    /**
     * Set the block threshold.
     */
    public function setBlockThreshold(float $threshold): static
    {
        $this->blockThreshold = max(0.0, min(1.0, $threshold));

        return $this;
    }

    /**
     * Get the current block threshold.
     */
    public function getBlockThreshold(): float
    {
        return $this->blockThreshold;
    }

    /**
     * Configure the velocity detector.
     *
     * @param  array<string, int>  $thresholds
     */
    public function configureVelocityDetector(array $thresholds): static
    {
        $detector = $this->getDetector('velocity');

        if ($detector instanceof VelocityDetector) {
            $detector->setThresholds($thresholds);
        }

        return $this;
    }

    /**
     * Configure the pattern detector.
     *
     * @param  array<int>  $unusualHours
     * @param  array<string>  $suspiciousIpPatterns
     */
    public function configurePatternDetector(
        array $unusualHours = [],
        array $suspiciousIpPatterns = [],
    ): static {
        $detector = $this->getDetector('pattern');

        if ($detector instanceof PatternDetector) {
            if (! empty($unusualHours)) {
                $detector->setUnusualHours($unusualHours);
            }
            if (! empty($suspiciousIpPatterns)) {
                $detector->setSuspiciousIpPatterns($suspiciousIpPatterns);
            }
        }

        return $this;
    }

    /**
     * Configure the behavioral detector.
     *
     * @param  array<string, mixed>  $config
     */
    public function configureBehavioralDetector(array $config): static
    {
        $detector = $this->getDetector('behavioral');

        if ($detector instanceof BehavioralDetector) {
            $detector->configure($config);
        }

        return $this;
    }

    /**
     * Configure the code abuse detector.
     *
     * @param  array<string, mixed>  $config
     */
    public function configureCodeAbuseDetector(array $config): static
    {
        $detector = $this->getDetector('code_abuse');

        if ($detector instanceof CodeAbuseDetector) {
            $detector->configure($config);
        }

        return $this;
    }

    /**
     * Apply configuration from array.
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): static
    {
        if (isset($config['block_threshold'])) {
            $this->setBlockThreshold((float) $config['block_threshold']);
        }

        if (isset($config['velocity'])) {
            $this->configureVelocityDetector($config['velocity']);
        }

        if (isset($config['pattern'])) {
            $patternConfig = $config['pattern'];
            $this->configurePatternDetector(
                $patternConfig['unusual_hours'] ?? [],
                $patternConfig['suspicious_ip_patterns'] ?? [],
            );
        }

        if (isset($config['behavioral'])) {
            $this->configureBehavioralDetector($config['behavioral']);
        }

        if (isset($config['code_abuse'])) {
            $this->configureCodeAbuseDetector($config['code_abuse']);
        }

        if (isset($config['disabled_detectors'])) {
            foreach ($config['disabled_detectors'] as $name) {
                $this->disableDetector($name);
            }
        }

        return $this;
    }

    /**
     * Register the default detectors.
     */
    private function registerDefaultDetectors(): void
    {
        $this->detectors = [
            'velocity' => new VelocityDetector,
            'pattern' => new PatternDetector,
            'behavioral' => new BehavioralDetector,
            'code_abuse' => new CodeAbuseDetector,
        ];
    }
}
