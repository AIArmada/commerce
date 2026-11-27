<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Api;

interface ManagesTestimonialDependencies
{
    public function deleteForResponse(string $responseId): void;

    public function deleteForAnswer(string $answerId): void;

    /** @param list<string> $answerIds */
    public function detachForAnswers(array $answerIds): void;
}
