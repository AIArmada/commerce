<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final readonly class CreateFeedbackFormData
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public string $purpose = 'general',
        public string $status = 'draft',
        public string $visibility = 'private',
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public bool $isAnonymousAllowed = true,
        public bool $isAnonymityOptional = false,
        public bool $isLoginRequired = false,
        public bool $isOneResponsePerRespondent = false,
        public bool $isEditAfterSubmitAllowed = false,
        public ?string $opensAt = null,
        public ?string $closesAt = null,
        public array $settings = [],
        public array $metadata = [],
    ) {}
}
