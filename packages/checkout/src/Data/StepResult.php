<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use AIArmada\Checkout\Enums\StepStatus;
use Spatie\LaravelData\Data;

final class StepResult extends Data
{
    /**
     * Marker for a result where the step did not run because another flow
     * already owns the outcome. The executor preserves the step's previous
     * state and emits no completion or failure event for it.
     */
    public const OBSERVED_KEY = 'observed';

    public function __construct(
        public StepStatus $status,
        public string $stepIdentifier,
        public ?string $message = null,
        /** @var array<string, mixed> */
        public array $data = [],
        /** @var array<string, string> */
        public array $errors = [],
        public ?PaymentFailureOrigin $failureOrigin = null,
        public ?PaymentFailureOrigin $executionOrigin = null,
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

    public static function failed(
        string $stepIdentifier,
        string $message,
        array $errors = [],
        ?PaymentFailureOrigin $failureOrigin = null,
    ): self {
        return new self(
            status: StepStatus::Failed,
            stepIdentifier: $stepIdentifier,
            message: $message,
            errors: $errors,
            failureOrigin: $failureOrigin,
        );
    }

    public function withFailureOrigin(PaymentFailureOrigin $origin): self
    {
        $this->failureOrigin = $origin;

        return $this;
    }

    public function withExecutionOrigin(PaymentFailureOrigin $origin): self
    {
        $this->executionOrigin = $origin;

        return $this;
    }

    /**
     * Create a result for a completed compensation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function compensated(string $stepIdentifier, ?string $message = null, array $data = []): self
    {
        return new self(
            status: StepStatus::RolledBack,
            stepIdentifier: $stepIdentifier,
            message: $message ?? 'Step compensated',
            data: $data,
        );
    }

    /**
     * Create a result for a step that observed an outcome already owned by
     * another flow and therefore neither ran nor failed.
     *
     * @param  array<string, mixed>  $data
     */
    public static function observed(string $stepIdentifier, ?string $message = null, array $data = []): self
    {
        return new self(
            status: StepStatus::Processing,
            stepIdentifier: $stepIdentifier,
            message: $message,
            data: array_merge([self::OBSERVED_KEY => true], $data),
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status->isComplete();
    }

    public function isObserved(): bool
    {
        return ($this->data[self::OBSERVED_KEY] ?? null) === true;
    }

    public function isCompensated(): bool
    {
        return $this->status === StepStatus::RolledBack;
    }
}
