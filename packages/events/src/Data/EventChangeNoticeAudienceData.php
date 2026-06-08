<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Spatie\LaravelData\Data;

final class EventChangeNoticeAudienceData extends Data
{
    /**
     * @param  array<int, string>  $registered
     * @param  array<int, string>  $waitlisted
     * @param  array<int, string>  $paid
     * @param  array<int, string>  $saved
     * @param  array<int, string>  $going
     * @param  array<int, string>  $interested
     */
    public function __construct(
        public readonly string $noticeId,
        public readonly string $eventId,
        public readonly array $registered = [],
        public readonly array $waitlisted = [],
        public readonly array $paid = [],
        public readonly array $saved = [],
        public readonly array $going = [],
        public readonly array $interested = [],
    ) {}
}
