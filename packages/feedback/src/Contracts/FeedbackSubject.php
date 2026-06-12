<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Contracts;

interface FeedbackSubject
{
    public function getFeedbackSubjectType(): string;

    public function getFeedbackSubjectId(): string;
}
