<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class BatchCreateOccurrencesAction
{
    use AsAction;

    public function __construct(
        private readonly EnsureOccurrenceAction $ensureOccurrence,
        private readonly EventTicketingDriver $ticketing,
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
        $resolved = EventWriteGuard::findOrFail($event);

        if (! $resolved instanceof Event) {
            throw new InvalidArgumentException('The configured events model must extend the Catalog Event model.');
        }

        return DB::transaction(function () use ($resolved, $occurrences, $ticketTypeTemplate): Collection {
            $eventClass = ModelResolver::eventClass();
            $locked = $eventClass::query()->whereKey($resolved->getKey())->lockForUpdate()->firstOrFail();

            EventWriteGuard::findOrFail($locked->getKey());

            $created = new Collection;

            foreach ($occurrences as $attributes) {
                if (! is_array($attributes)) {
                    throw new InvalidArgumentException('Each occurrence definition must be an array of attributes.');
                }

                $occurrence = $this->ensureOccurrence->handle($resolved, $attributes);

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

                    $this->ticketing->ensureTicketType(
                        'occurrence',
                        (string) $occurrence->getKey(),
                        $ticketTypeAttributes,
                    );
                }

                $created->push($occurrence);
            }

            return $created;
        });
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
