<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventPassIssuanceService;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Data\EventPassIssuanceData;
use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Registrations\Api\EventRegistrationParticipantData;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Features\Registrations\Api\RegistrationSummary;
use AIArmada\Events\Platform\EventTicketScope;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\LifecyclePolicy;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class IssueEventRegistrationPassesAction implements EventPassIssuanceService
{
    public function __construct(
        private readonly RegistrationsApi $registrations,
        private readonly EventTicketingDriver $ticketing,
        private readonly CatalogApi $catalog,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly LifecyclePolicy $lifecycle,
    ) {}

    /**
     * @return list<string>
     */
    public function issueForRegistration(string $registrationId): array
    {
        return $this->handle($registrationId);
    }

    /**
     * Issue every admission pass the registration is entitled to.
     *
     * When `$respectFreePassPolicy` is set, registrations whose event scope
     * opts out of free pass issuance are skipped.
     *
     * @return list<string>
     */
    public function handle(string $registrationId, bool $respectFreePassPolicy = false): array
    {
        return DB::transaction(function () use ($registrationId, $respectFreePassPolicy): array {
            $initial = $this->registrations->summarize($registrationId);

            if ($initial === null) {
                throw new InvalidArgumentException(sprintf('Registration [%s] does not exist.', $registrationId));
            }

            EventWriteGuard::findOrFail($initial->eventId);

            $scopeRef = $this->scopeRef($initial);

            $this->registrations->lockScope($scopeRef['type'], $scopeRef['id']);
            $this->registrations->lock($registrationId);

            $summary = $this->registrations->summarize($registrationId);

            if ($summary === null) {
                throw new InvalidArgumentException(sprintf('Registration [%s] does not exist.', $registrationId));
            }

            if (! $this->lifecycle->canIssuePasses($summary->status)) {
                return [];
            }

            $scope = $this->scopeResolver->resolve($scopeRef['type'], $scopeRef['id']);

            if ($respectFreePassPolicy && ! $scope->shouldIssuePasses) {
                return [];
            }

            $registrationType = $this->registrations->registrationMorphClass();
            $existing = $this->registrations->passIdsForRegistration($registrationId);
            $items = $this->registrations->orderItemsForRegistration($registrationId);

            if ($items === []) {
                $ticketTypeId = $this->ensureFreeTicketType($scope, $scopeRef);

                return $this->issueForTicket(
                    summary: $summary,
                    registrationType: $registrationType,
                    ticketTypeId: $ticketTypeId,
                    quantity: 1,
                    metadata: ['issued_from' => 'free_registration'],
                    existingIds: $existing,
                );
            }

            $quantities = [];
            $itemIds = [];

            foreach ($items as $item) {
                $ticketTypeId = $item->ticketTypeId;

                if ($ticketTypeId === null || $ticketTypeId === '') {
                    throw new InvalidArgumentException('Registration items must reference a TicketType.');
                }

                $ticketType = TicketType::query()->with('ticketable')->find($ticketTypeId);

                if (! $ticketType instanceof TicketType) {
                    throw new InvalidArgumentException('Registration items must reference an existing TicketType.');
                }

                if (! EventTicketScope::belongsToRegistrationScope($ticketType, $scope)) {
                    throw new InvalidArgumentException('Registration items must reference a ticket type that belongs to the same event scope.');
                }

                $admits = max(1, (int) $ticketType->admits_quantity);
                $quantities[$ticketTypeId] = ($quantities[$ticketTypeId] ?? 0) + max(1, $item->quantity) * $admits;
                $itemIds[$ticketTypeId][] = $item->id;
            }

            $issued = $existing;

            foreach ($quantities as $ticketTypeId => $quantity) {
                $issued = array_values(array_unique(array_merge($issued, $this->issueForTicket(
                    summary: $summary,
                    registrationType: $registrationType,
                    ticketTypeId: (string) $ticketTypeId,
                    quantity: $quantity,
                    metadata: array_filter([
                        'registration_item_ids' => $itemIds[$ticketTypeId] ?? [],
                        'registration_item_id' => $itemIds[$ticketTypeId][0] ?? null,
                        'ticket_type_id' => (string) $ticketTypeId,
                    ], static fn (mixed $value): bool => $value !== null),
                    existingIds: $issued,
                ))));
            }

            return $issued;
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $existingIds
     * @return list<string>
     */
    private function issueForTicket(
        RegistrationSummary $summary,
        string $registrationType,
        string $ticketTypeId,
        int $quantity,
        array $metadata,
        array $existingIds,
    ): array {
        $existingForTicket = $this->ticketing->countPassesForRegistrationTicket(
            registrationType: $registrationType,
            registrationId: $summary->id,
            ticketTypeId: $ticketTypeId,
        );

        $remaining = max(0, $quantity - $existingForTicket);

        if ($remaining === 0) {
            return $existingIds;
        }

        $newPassIds = $this->ticketing->issuePasses(new EventPassIssuanceData(
            ticketTypeId: $ticketTypeId,
            quantity: $remaining,
            holderAttributes: $this->holderAttributes($summary->id, $remaining, $existingForTicket),
            metadata: $metadata,
            registrationType: $registrationType,
            registrationId: $summary->id,
            occurrenceId: $summary->occurrenceId,
            sessionId: $summary->sessionId,
        ));

        return array_values(array_unique(array_merge($existingIds, $newPassIds)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function holderAttributes(string $registrationId, int $quantity, int $offset): array
    {
        $holders = array_values(array_filter(array_map(
            static fn (EventRegistrationParticipantData $participant): array => array_filter([
                'holder_type' => $participant->morphClass,
                'holder_id' => $participant->id,
                'name' => $participant->name !== '' ? $participant->name : null,
                'email' => $participant->email,
            ], static fn (mixed $value): bool => $value !== null),
            $this->registrations->participantsForRegistration($registrationId),
        )));

        if ($holders === [] || $quantity < 1) {
            return [];
        }

        $holderCount = count($holders);
        $start = max(0, $offset) % $holderCount;
        $assigned = [];

        for ($index = 0; $index < $quantity; $index++) {
            $assigned[] = $holders[($start + $index) % $holderCount];
        }

        return $assigned;
    }

    /**
     * @param  array{type: string, id: string}  $scopeRef
     */
    private function ensureFreeTicketType(EventRegistrationScopeData $scope, array $scopeRef): string
    {
        $title = $this->catalog->describeScope($scopeRef['type'], $scopeRef['id'])?->title;

        return $this->ticketing->ensureTicketType($scopeRef['type'], $scopeRef['id'], [
            'code' => 'free-' . $scopeRef['id'],
            'name' => ($title ?? $scopeRef['id']) . ' Free Pass',
            'description' => 'Auto-generated free admission pass type.',
            'access_type' => 'general',
            'price' => 0,
            'currency' => config('events.defaults.currency', config('ticketing.defaults.currency', 'MYR')),
            'status' => 'active',
            'visibility' => 'private',
            'sort_order' => 0,
        ]);
    }

    /**
     * @return array{type: string, id: string}
     */
    private function scopeRef(RegistrationSummary $summary): array
    {
        if ($summary->sessionId !== null) {
            return ['type' => 'session', 'id' => $summary->sessionId];
        }

        if ($summary->occurrenceId !== null) {
            return ['type' => 'occurrence', 'id' => $summary->occurrenceId];
        }

        return ['type' => 'event', 'id' => $summary->eventId];
    }
}
