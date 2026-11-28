<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Api;

interface ReadsSubjectTestimonials
{
    /** @return list<array{id: string, quote: string, display_name: ?string, display_title: ?string, display_organization: ?string, rating: ?float}> */
    public function published(string $type, string $id): array;
}
