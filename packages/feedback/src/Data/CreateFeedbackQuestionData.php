<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final readonly class CreateFeedbackQuestionData
{
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public ?string $description = null,
        public ?string $helpText = null,
        public ?string $placeholder = null,
        public bool $isRequired = false,
        public bool $isScored = false,
        public int $orderColumn = 0,
        public ?string $sectionId = null,
        public array $validationRules = [],
        public array $visibilityRules = [],
        public array $scoringRules = [],
        public array $settings = [],
        public array $metadata = [],
    ) {}
}
