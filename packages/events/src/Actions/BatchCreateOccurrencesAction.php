<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

final class BatchCreateOccurrencesAction
{
    use AsAction;

    public function __construct(
        private readonly EnsureOccurrenceAction $ensureOccurrence,
        private readonly EnsureTicketTypeForOccurrenceAction $ensureTicketType,
    ) {}

    /**
     * Ensure multiple occurrences exist on an event, optionally creating
     * ticket types for each from a shared template.
     *
     * @param  array<int, array<string, mixed>>  $occurrences  Per-occurrence attributes.
     *                                                         Each entry supports all EnsureOccurrenceAction fields
     *                                                         plus optional overrides for ticket type creation ('code', 'price', etc.).
     * @param  array<string, mixed>|null  $ticketTypeTemplate  When set, a ticket type is created for each
     *                                                         occurrence. Values here serve as defaults;
     *                                                         per-occurrence attributes override them.
     * @return Collection<int, EventOccurrence>
     */
    public function handle(
        Event $event,
        array $occurrences,
        ?array $ticketTypeTemplate = null,
    ): Collection {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $event->id);

        $created = new Collection;

        foreach ($occurrences as $attributes) {
            $occurrence = $this->ensureOccurrence->handle($event, $attributes);

            if ($ticketTypeTemplate !== null) {
                $ticketTypeAttributes = array_merge(
                    $ticketTypeTemplate,
                    array_intersect_key(
                        $attributes,
                        array_flip(['code', 'price', 'currency', 'quota', 'name', 'description', 'min_quantity', 'max_quantity']),
                    ),
                );

                if (blank($ticketTypeAttributes['code'] ?? null)) {
                    $templateCode = $ticketTypeTemplate['code'] ?? null;

                    $ticketTypeAttributes['code'] = blank($templateCode)
                        ? $this->defaultCode($occurrence)
                        : $templateCode;
                }

                $this->ensureTicketType->handle(
                    $occurrence,
                    $ticketTypeAttributes,
                );
            }

            $created->push($occurrence);
        }

        return $created;
    }

    private function defaultCode(EventOccurrence $occurrence): string
    {
        $startsAt = $occurrence->starts_at;

        $datePart = $startsAt instanceof CarbonImmutable
            ? $startsAt->format('Ymd')
            : (is_string($startsAt) ? str_replace('-', '', $startsAt) : $occurrence->getKey());

        return sprintf('ticket-%s', $datePart);
    }
}
