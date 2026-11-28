<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Api;

interface ReadsTestimonialMetrics
{
    /** @return array<string, int> */
    public function counts(): array;
}
