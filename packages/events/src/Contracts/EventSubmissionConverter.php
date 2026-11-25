<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventSubmissionConverter
{
    public function convert(string $submissionId): string;
}
