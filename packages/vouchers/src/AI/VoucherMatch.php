<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI;

use AIArmada\Vouchers\Models\Voucher;

/**
 * Value object representing a voucher match recommendation.
 *
 * @property-read Voucher|null $voucher The matched voucher
 * @property-read float $matchScore How well the voucher matches (0.0 to 1.0)
 * @property-read array<string, mixed> $matchReasons Why this voucher was matched
 * @property-read array<int, array<string, mixed>> $alternatives Alternative voucher options
 */
final readonly class VoucherMatch
{
    /**
     * @param  array<string, mixed>  $matchReasons
     * @param  array<int, array<string, mixed>>  $alternatives
     */
    public function __construct(
        public ?Voucher $voucher,
        public float $matchScore,
        public array $matchReasons = [],
        public array $alternatives = [],
    ) {}

    /**
     * Create an empty match (no suitable voucher found).
     */
    public static function none(): self
    {
        return new self(
            voucher: null,
            matchScore: 0.0,
            matchReasons: ['no_match' => 'No suitable voucher found'],
        );
    }

    /**
     * Create a perfect match.
     *
     * @param  array<string, mixed>  $reasons
     */
    public static function perfect(Voucher $voucher, array $reasons = []): self
    {
        return new self(
            voucher: $voucher,
            matchScore: 1.0,
            matchReasons: array_merge(['match_type' => 'perfect'], $reasons),
        );
    }

    /**
     * Create a good match.
     *
     * @param  array<string, mixed>  $reasons
     */
    public static function good(Voucher $voucher, float $score = 0.75, array $reasons = []): self
    {
        return new self(
            voucher: $voucher,
            matchScore: $score,
            matchReasons: array_merge(['match_type' => 'good'], $reasons),
        );
    }

    /**
     * Check if a match was found.
     */
    public function hasMatch(): bool
    {
        return $this->voucher !== null;
    }

    /**
     * Check if this is a strong match (score >= 0.7).
     */
    public function isStrongMatch(): bool
    {
        return $this->matchScore >= 0.7;
    }

    /**
     * Check if this is a weak match (score < 0.5).
     */
    public function isWeakMatch(): bool
    {
        return $this->hasMatch() && $this->matchScore < 0.5;
    }

    /**
     * Check if there are alternatives available.
     */
    public function hasAlternatives(): bool
    {
        return count($this->alternatives) > 0;
    }

    /**
     * Get the voucher code if matched.
     */
    public function getCode(): ?string
    {
        return $this->voucher?->code;
    }

    /**
     * Get the discount amount if matched.
     */
    public function getDiscountAmount(): ?int
    {
        return $this->voucher?->value;
    }

    /**
     * Get the top match reasons.
     *
     * @return array<string, mixed>
     */
    public function getTopReasons(int $limit = 3): array
    {
        return array_slice($this->matchReasons, 0, $limit, true);
    }

    /**
     * Get the best alternative voucher.
     *
     * @return array<string, mixed>|null
     */
    public function getBestAlternative(): ?array
    {
        if (! $this->hasAlternatives()) {
            return null;
        }

        return $this->alternatives[0] ?? null;
    }

    /**
     * Get a summary of the match.
     */
    public function getSummary(): string
    {
        if (! $this->hasMatch()) {
            return 'No voucher match found';
        }

        $code = $this->voucher->code;
        $score = round($this->matchScore * 100);

        return "Matched: {$code} (score: {$score}%)";
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'voucher_id' => $this->voucher?->id,
            'voucher_code' => $this->voucher?->code,
            'match_score' => $this->matchScore,
            'match_reasons' => $this->matchReasons,
            'alternatives' => $this->alternatives,
            'has_match' => $this->hasMatch(),
            'is_strong_match' => $this->isStrongMatch(),
        ];
    }
}
