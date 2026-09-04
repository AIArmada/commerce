<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationParticipant;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventRegistrationScope;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Ticketing\Actions\EnsureTicketTypeAction;
use AIArmada\Ticketing\Actions\IssuePassesAction;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class IssueEventRegistrationPassesAction
{
    public function __construct(
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly EnsureTicketTypeAction $ensureTicketType,
        private readonly IssuePassesAction $issuePasses,
    ) {}

    /** @return Collection<int, Pass> */
    public function handle(EventRegistration $registration): Collection
    {
        return DB::transaction(function () use ($registration): Collection {
            EventWriteGuard::findOrFail($registration->event_id);

            $registration->loadMissing(
                'items.ticketType.ticketable',
                'participants.contactMethods',
                'event.seatMaps',
                'occurrence.seatMaps',
                'session.seatMaps',
            );

            $registration->setRelation('passes', $registration->passes()->lockForUpdate()->get());

            $scope = $this->resolveScope($registration);
            $issued = new Collection;

            if ($registration->items->isEmpty()) {
                $ticketType = $this->ensureFreeTicketType($scope);

                return $this->issueForRegistration(
                    registration: $registration,
                    ticketType: $ticketType,
                    quantity: 1,
                    metadata: ['issued_from' => 'free_registration'],
                );
            }

            foreach ($registration->items as $item) {
                $ticketType = $item->ticketType;

                if (! $ticketType instanceof TicketType) {
                    throw new InvalidArgumentException('Registration items must reference a TicketType.');
                }

                $ticketType->loadMissing('ticketable');

                if (! EventTicketScope::belongsToRegistrationScope($ticketType, $scope)) {
                    throw new InvalidArgumentException('Registration items must reference a ticket type that belongs to the same event scope.');
                }

                $quantity = max(1, $item->quantity * max(1, $ticketType->admits_quantity));

                $passes = $this->issueForRegistration(
                    registration: $registration,
                    ticketType: $ticketType,
                    quantity: $quantity,
                    metadata: array_filter([
                        'registration_item_id' => $item->getKey(),
                        'ticket_type_id' => $item->ticket_type_id,
                    ], static fn (mixed $value): bool => $value !== null),
                );

                $issued = $issued->merge($passes);
            }

            return $issued->values();
        });
    }

    private function resolveScope(EventRegistration $registration): EventRegistrationScope
    {
        $target = $registration->session
            ?? $registration->occurrence
            ?? $registration->event;

        return $this->scopeResolver->resolve($target);
    }

    private function ensureFreeTicketType(EventRegistrationScope $scope): TicketType
    {
        $target = $scope->session
            ?? $scope->occurrence
            ?? $scope->event;

        $label = match (true) {
            $target instanceof EventSession => $target->title,
            $target instanceof EventOccurrence => $target->title,
            default => $scope->event->title,
        };

        return $this->ensureTicketType->handle($target, [
            'code' => 'free-' . $target->getKey(),
            'name' => $label . ' Free Pass',
            'description' => 'Auto-generated free admission pass type.',
            'access_type' => 'general',
            'price' => 0,
            'currency' => config('events.defaults.currency', config('ticketing.defaults.currency', 'MYR')),
            'status' => 'active',
            'visibility' => 'private',
            'sort_order' => 0,
        ]);
    }

    /** @return Collection<int, Pass> */
    private function issueForRegistration(
        EventRegistration $registration,
        TicketType $ticketType,
        int $quantity,
        array $metadata = [],
    ): Collection {
        $existing = $registration->passes
            ->filter(fn (Pass $pass): bool => (string) $pass->ticket_type_id === (string) $ticketType->getKey())
            ->values();
        $remaining = max(0, $quantity - $existing->count());

        if ($remaining === 0) {
            return $existing;
        }

        $passes = $this->issuePasses->handle(new PassIssuanceContext(
            ticketType: $ticketType,
            quantity: $remaining,
            holderAttributes: $this->holderAttributesFor($registration, $remaining, $existing->count()),
            metadata: $metadata,
            registrationType: $registration->getMorphClass(),
            registrationId: $registration->getKey(),
            occurrenceId: $registration->event_occurrence_id,
            sessionId: $registration->event_session_id,
        ));

        $registration->setRelation('passes', $registration->passes->merge($passes));

        return $existing->merge($passes)->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function holderAttributesFor(EventRegistration $registration, int $quantity, int $offset = 0): array
    {
        $holders = $registration->participants
            ->map(fn (EventRegistrationParticipant $participant): array => array_filter([
                'holder_type' => $participant->getMorphClass(),
                'holder_id' => $participant->getKey(),
                'name' => $participant->name,
                'email' => $this->resolveParticipantEmail($participant),
            ], static fn (mixed $value): bool => $value !== null))
            ->filter(static fn (array $holder): bool => $holder !== [])
            ->values()
            ->all();

        if ($holders === []) {
            return [];
        }

        if ($quantity < 1) {
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

    private function resolveParticipantEmail(EventRegistrationParticipant $participant): ?string
    {
        $email = $participant->hasAttribute('email')
            ? $participant->getAttribute('email')
            : null;

        if (is_string($email) && mb_trim($email) !== '') {
            return mb_trim($email);
        }

        if (! method_exists($participant, 'contactMethods')) {
            return null;
        }

        $contactMethod = null;

        if ($participant->relationLoaded('contactMethods')) {
            /** @var EloquentCollection<int, ContactMethod> $contactMethods */
            $contactMethods = $participant->getRelation('contactMethods');

            $contactMethod = $contactMethods
                ->filter(static fn (ContactMethod $contactMethod): bool => $contactMethod->type === 'email')
                ->sort(function (ContactMethod $left, ContactMethod $right): int {
                    return ($right->is_primary <=> $left->is_primary)
                        ?: ($left->sort_order <=> $right->sort_order);
                })
                ->first();
        } else {
            /** @var MorphMany $contactMethods */
            $contactMethods = $participant->contactMethods();
            $contactMethod = $contactMethods
                ->where('type', 'email')
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->first();
        }

        if (! $contactMethod instanceof ContactMethod) {
            return null;
        }

        $value = $contactMethod?->normalized_value ?? $contactMethod?->value;

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }
}
