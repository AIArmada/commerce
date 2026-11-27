<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Api;

final readonly class QuestionDefinition
{
    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $visibilityRules
     * @param array<string, mixed> $scoringRules
     * @param list<array{value: string, score: float|null}> $options
     */
    public function __construct(
        public string $id,
        public string $key,
        public string $type,
        public bool $isRequired,
        public bool $isScored,
        public array $settings = [],
        public array $visibilityRules = [],
        public array $scoringRules = [],
        public array $options = [],
    ) {}
}
