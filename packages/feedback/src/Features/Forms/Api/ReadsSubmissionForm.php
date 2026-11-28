<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Api;

interface ReadsSubmissionForm
{
    public function requireOwned(string $formId, bool $includeQuestions = true): SubmissionFormDefinition;
}
