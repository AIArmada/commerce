<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Contracts;

interface FeedbackRespondent
{
    public function getFeedbackRespondentType(): string;

    public function getFeedbackRespondentId(): string;
}
