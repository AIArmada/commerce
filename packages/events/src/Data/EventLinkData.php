<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventLink;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class EventLinkData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $link_type,
        public readonly string | null | Optional $label,
        public readonly string $url,
    ) {}

    public static function fromEventLink(EventLink $link): self
    {
        return new self(
            id: $link->id,
            link_type: $link->link_type,
            label: $link->label,
            url: $link->url,
        );
    }
}
