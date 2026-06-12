<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final class CreateFeedbackQuestionData
{
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $label,
        public readonly ?string $description = null,
        public readonly ?string $helpText = null,
        public readonly ?string $placeholder = null,
        public readonly bool $isRequired = false,
        public readonly bool $isScored = false,
        public readonly int $orderColumn = 0,
        public readonly ?string $sectionId = null,
        public readonly array $validationRules = [],
        public readonly array $visibilityRules = [],
        public readonly array $scoringRules = [],
        public readonly array $settings = [],
        public readonly array $metadata = [],
    ) {}
}
