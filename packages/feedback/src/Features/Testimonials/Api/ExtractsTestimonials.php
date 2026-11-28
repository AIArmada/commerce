<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Api;

interface ExtractsTestimonials
{
    public function execute(string $responseId): ?string;
}
