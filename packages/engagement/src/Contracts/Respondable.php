<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface Respondable
{
    /** @return array<string> */
    public function allowedResponseTypes(): array;

    public function defaultResponseVisibility(): string;

    public function allowsMultipleResponsesFromSameResponder(): bool;
}
