<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final class CreateFeedbackFormData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $slug = null,
        public readonly string $purpose = 'general',
        public readonly string $status = 'draft',
        public readonly string $visibility = 'private',
        public readonly ?string $subjectType = null,
        public readonly ?string $subjectId = null,
        public readonly bool $isAnonymousAllowed = true,
        public readonly bool $isAnonymityOptional = false,
        public readonly bool $isLoginRequired = false,
        public readonly bool $isOneResponsePerRespondent = false,
        public readonly bool $isEditAfterSubmitAllowed = false,
        public readonly ?string $opensAt = null,
        public readonly ?string $closesAt = null,
        public readonly array $settings = [],
        public readonly array $metadata = [],
    ) {}
}
