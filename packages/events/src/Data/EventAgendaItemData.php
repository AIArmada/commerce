<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventAgenda;
use Spatie\LaravelData\Data;

final class EventAgendaItemData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $segmentKey,
        public readonly ?string $segmentType = null,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $startsAt = null,
        public readonly ?string $endsAt = null,
        public readonly ?int $durationMinutes = null,
        public readonly ?int $orderColumn = null,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromAgendaItem(EventAgenda $agendaItem): self
    {
        return new self(
            id: $agendaItem->id,
            segmentKey: $agendaItem->segment_key,
            segmentType: $agendaItem->segment_type,
            title: $agendaItem->title,
            description: $agendaItem->description,
            startsAt: $agendaItem->starts_at?->toISOString(),
            endsAt: $agendaItem->ends_at?->toISOString(),
            durationMinutes: $agendaItem->duration_minutes,
            orderColumn: $agendaItem->order_column,
            metadata: $agendaItem->metadata,
        );
    }
}
