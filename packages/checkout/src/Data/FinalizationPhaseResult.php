<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

final class FinalizationPhaseResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $error = null,
    ) {}

    public static function ok(): self
    {
        return new self(success: true);
    }

    public static function failed(string $error): self
    {
        return new self(success: false, error: $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error' => $this->error,
        ];
    }
}
