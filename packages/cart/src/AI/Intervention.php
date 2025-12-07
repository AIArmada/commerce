<?php

declare(strict_types=1);

namespace AIArmada\Cart\AI;

/**
 * Represents a recommended intervention for cart recovery.
 */
final readonly class Intervention
{
    /**
     * @param  string  $type  Intervention type: email, discount, push_notification, exit_intent, sms
     * @param  int  $priority  Priority (1 = highest)
     * @param  string  $message  Human-readable description
     * @param  array<string, mixed>  $parameters  Intervention-specific parameters
     */
    public function __construct(
        public string $type,
        public int $priority,
        public string $message,
        public array $parameters = []
    ) {}

    /**
     * Check if this is an immediate intervention (should happen now).
     */
    public function isImmediate(): bool
    {
        $delayMinutes = $this->parameters['delay_minutes'] ?? 0;

        return $delayMinutes === 0;
    }

    /**
     * Get the delay before this intervention should trigger.
     */
    public function getDelayMinutes(): int
    {
        return $this->parameters['delay_minutes'] ?? 0;
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
            'priority' => $this->priority,
            'message' => $this->message,
            'parameters' => $this->parameters,
            'is_immediate' => $this->isImmediate(),
        ];
    }
}
