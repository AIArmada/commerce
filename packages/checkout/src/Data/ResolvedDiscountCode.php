<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

final readonly class ResolvedDiscountCode
{
    /**
     * @param  array<string, mixed>|null  $voucher
     */
    private function __construct(
        public string $kind,
        public ?string $code = null,
        public ?array $voucher = null,
        public ?object $promotion = null,
    ) {}

    public static function none(): self
    {
        return new self(kind: 'none');
    }

    /**
     * @param  array<string, mixed>  $voucher
     */
    public static function voucher(string $code, array $voucher): self
    {
        return new self(kind: 'voucher', code: $code, voucher: $voucher);
    }

    public static function promotion(string $code, object $promotion): self
    {
        return new self(kind: 'promotion', code: $code, promotion: $promotion);
    }

    public function isVoucher(): bool
    {
        return $this->kind === 'voucher';
    }

    public function isPromotion(): bool
    {
        return $this->kind === 'promotion';
    }
}
