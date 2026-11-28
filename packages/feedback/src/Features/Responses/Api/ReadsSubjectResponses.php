<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Api;

interface ReadsSubjectResponses
{
    /** @return list<string> */
    public function subjectIds(string $type, string $id): array;

    /** @return list<string> */
    public function respondentIds(string $type, string $id): array;

    public function hasSubmitted(string $respondentType, string $respondentId, string $subjectType, string $subjectId, ?string $formId = null): bool;

    public function latestId(string $respondentType, string $respondentId, string $subjectType, string $subjectId, ?string $formId = null): ?string;
}
