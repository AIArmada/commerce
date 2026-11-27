<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Api;

interface ManagesTestimonialDependencies
{
    public function deleteForForm(string $formId): void;

    public function deleteForResponse(string $responseId): void;

    public function deleteForAnswer(string $answerId): void;

    public function detachForQuestion(string $questionId): void;
}
