<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Api;

interface CreatesFormsFromTemplates
{
    /** @param array<string, mixed> $overrides */
    public function createForSubject(string $templateSlug, string $subjectType, string $subjectId, array $overrides = []): string;
}
