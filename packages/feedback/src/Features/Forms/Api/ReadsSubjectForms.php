<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Api;

interface ReadsSubjectForms
{
    /** @return list<string> */
    public function ids(string $type, string $id): array;
}
