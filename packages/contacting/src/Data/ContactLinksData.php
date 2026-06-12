<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ContactLinksData extends Data
{
    public function __construct(
        public readonly string | null | Optional $mailtoUrl = null,
        public readonly string | null | Optional $telUrl = null,
        public readonly string | null | Optional $whatsappUrl = null,
        public readonly string | null | Optional $websiteUrl = null,
        public readonly array $links = [],
    ) {}
}
