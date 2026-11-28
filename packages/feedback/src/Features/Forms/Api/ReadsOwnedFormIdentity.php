<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Api;

interface ReadsOwnedFormIdentity
{
    public function find(string $formId): ?OwnedFormIdentity;

    public function requireOwned(string $formId, bool $lock = false): OwnedFormIdentity;

    public function requireOwnedQuestion(string $questionId): void;

    /** @param list<string> $questionIds
     * @return array<string, QuestionDefinition>
     */
    public function questionDefinitions(string $formId, array $questionIds): array;
}
