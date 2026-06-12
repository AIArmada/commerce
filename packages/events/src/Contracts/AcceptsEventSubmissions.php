<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface AcceptsEventSubmissions
{
    public function canAcceptEventSubmission(mixed $submitter): bool;

    public function defaultSubmissionStatus(): string;

    public function eventSubmissionApprovers(): iterable;
}
