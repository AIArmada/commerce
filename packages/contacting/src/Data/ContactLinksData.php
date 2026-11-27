<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;


final readonly class ContactLinksData
{
    public function __construct(
        public ?string $mailtoUrl = null,
        public ?string $telUrl = null,
        public ?string $whatsappUrl = null,
        public ?string $websiteUrl = null,
        public array $links = [],
    ) {}
}
