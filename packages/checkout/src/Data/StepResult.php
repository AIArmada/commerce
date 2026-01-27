<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use AIArmada\Checkout\Enums\StepStatus;
use Spatie\LaravelData\Data;

final class StepResult extends Data
{
    public function __construct(
        public StepStatus $status,
        public string $stepIdentifier,
        public ?string $message = null,
        /** @var array<string, mixed> */
        public array $data = [],
        /** @var array<string, string> */
        public array $errors = [],
    ) {}

    public static function success(string $stepIdentifier, ?string $message = null, array $data = []): self
    {
        return new self(
            status: StepStatus::Completed,
            stepIdentifier: $stepIdentifier,
            message: $message,
            data: $data,
        );
    }

    public static function skipped(string $stepIdentifier, ?string $message = null): self
    {
        return new self(
            status: StepStatus::Skipped,
            stepIdentifier: $stepIdentifier,
            message: $message ?? 'Step skipped',
        );
    }

    public static function failed(string $stepIdentifier, string $message, array $errors = []): self
    {
        return new self(
            status: StepStatus::Failed,
            stepIdentifier: $stepIdentifier,
            message: $message,
            errors: $errors,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status->isComplete();
    }
}
