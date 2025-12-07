<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud;

/**
 * Represents a detected fraud signal.
 */
final readonly class FraudSignal
{
    public function __construct(
        public string $type,
        public string $detector,
        public int $score,
        public string $message,
        public ?string $recommendation = null,
        public array $metadata = []
    ) {}

    /**
     * Create a high severity signal.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function high(
        string $type,
        string $detector,
        string $message,
        ?string $recommendation = null,
        array $metadata = []
    ): self {
        return new self($type, $detector, 80, $message, $recommendation, $metadata);
    }

    /**
     * Create a medium severity signal.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function medium(
        string $type,
        string $detector,
        string $message,
        ?string $recommendation = null,
        array $metadata = []
    ): self {
        return new self($type, $detector, 50, $message, $recommendation, $metadata);
    }

    /**
     * Create a low severity signal.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function low(
        string $type,
        string $detector,
        string $message,
        ?string $recommendation = null,
        array $metadata = []
    ): self {
        return new self($type, $detector, 25, $message, $recommendation, $metadata);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'detector' => $this->detector,
            'score' => $this->score,
            'message' => $this->message,
            'recommendation' => $this->recommendation,
            'metadata' => $this->metadata,
        ];
    }
}
