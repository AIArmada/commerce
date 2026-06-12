<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

trait AcceptsEventSubmissions
{
    public function canAcceptEventSubmission(mixed $submitter): bool
    {
        return true;
    }

    public function defaultSubmissionStatus(): string
    {
        return 'pending';
    }

    public function eventSubmissionApprovers(): iterable
    {
        return [];
    }
}
