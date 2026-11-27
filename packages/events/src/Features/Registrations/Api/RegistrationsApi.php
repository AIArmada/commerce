<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Api;

interface RegistrationsApi
{
    public function registrationMorphClass(): string;

    public function summarize(string $registrationId): ?RegistrationSummary;

    /**
     * @param  list<string>|null  $registrationIds
     * @return list<RegistrationSummary>
     */
    public function forOrder(string $orderId, string $orderType, ?array $registrationIds = null): array;

    /**
     * @return list<string>
     */
    public function registrationIdsForOrderItem(
        string $eventId,
        ?string $occurrenceId,
        ?string $sessionId,
        string $orderItemId,
        string $orderItemType,
    ): array;

    /**
     * @return list<string>
     */
    public function registrationIdsWithOrderLink(string $scopeType, string $scopeId): array;

    /**
     * @param  list<string>  $ticketTypeIds
     * @return list<string>
     */
    public function registrationIdsForOrderWithTicketTypes(
        string $orderId,
        string $orderType,
        array $ticketTypeIds,
    ): array;

    /**
     * @return list<EventRegistrationOrderItemRef>
     */
    public function orderItemsForRegistration(string $registrationId): array;

    public function findOrderItem(string $registrationItemId): ?EventRegistrationOrderItemRef;

    /**
     * @return list<EventRegistrationParticipantData>
     */
    public function participantsForRegistration(string $registrationId): array;

    public function findParticipant(string $participantId): ?EventRegistrationParticipantData;

    /**
     * @return list<string>
     */
    public function passIdsForRegistration(string $registrationId): array;

    public function lock(string $registrationId): void;

    public function lockScope(string $scopeType, string $scopeId): void;

    public function capacityRemaining(
        string $eventId,
        ?string $occurrenceId,
        ?string $sessionId,
        ?int $capacity,
    ): ?int;

    public function recordPaymentStatus(string $registrationId, string $paymentStatus): void;

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    public function registerForFree(
        string $scopeType,
        string $scopeId,
        array $participants,
        ?string $registrantType = null,
        ?string $registrantId = null,
        array $options = [],
    ): array;

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    public function expandComponents(string $parentRegistrationId, array $options = []): array;

    public function authorizeRefundRequest(mixed $actor, string $registrationId): void;
}
