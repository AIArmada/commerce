<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Api;

interface ReadsTestimonialSource
{
    public function lockForExtraction(string $responseId): ?TestimonialSource;
}
