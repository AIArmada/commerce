<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Spatie\LaravelData\Data;

final class EventSearchResultData extends Data
{
    /**
     * @param  array<int, EventSearchCardData>  $items
     */
    public function __construct(
        public readonly array $items = [],
        public readonly int $total = 0,
        public readonly int $page = 1,
        public readonly int $perPage = 20,
    ) {}

    /**
     * @param  array<int, EventSearchCardData>  $items
     */
    public static function fromCards(array $items, int $total, int $page, int $perPage): self
    {
        return new self($items, $total, $page, $perPage);
    }

    public function hasMore(): bool
    {
        return ($this->page * $this->perPage) < $this->total;
    }
}
