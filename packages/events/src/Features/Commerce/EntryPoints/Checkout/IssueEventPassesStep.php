<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Checkout;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\AbstractCheckoutStep;
use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Features\Commerce\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Platform\EventTicketScope;
use AIArmada\Ticketing\Models\TicketType;

final class IssueEventPassesStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly IssueEventRegistrationPassesAction $issuePasses,
        private readonly EventTicketingDriver $ticketing,
        private readonly RegistrationsApi $registrations,
        private readonly EventOrderDriver $orders,
    ) {}

    public function getIdentifier(): string
    {
        return 'issue_event_passes';
    }

    public function getName(): string
    {
        return 'Issue Event Passes';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['create_event_registrations'];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        if ($session->order_id === null) {
            return $this->skipped('No order to issue passes for.');
        }

        $order = $this->orders->orderSnapshot((string) $session->order_id);

        if ($order === null) {
            return $this->skipped('Order not found.');
        }

        $issued = 0;
        $stepData = $session->getStepData($this->getIdentifier());
        $stepData['pass_ids'] = $this->stringList($stepData['pass_ids'] ?? []);

        $ticketTypeIds = [];

        foreach ($order->items as $orderItem) {
            if ($orderItem->ticketTypeId === null) {
                continue;
            }

            $ticketType = TicketType::query()->with('ticketable')->find($orderItem->ticketTypeId);

            if (! $ticketType instanceof TicketType || EventTicketScope::target($ticketType) === null) {
                continue;
            }

            $ticketTypeIds[] = $orderItem->ticketTypeId;
        }

        $ticketTypeIds = array_values(array_unique($ticketTypeIds));

        if ($ticketTypeIds !== []) {
            $registrationIds = $this->registrations->registrationIdsForOrderWithTicketTypes(
                orderId: $order->id,
                orderType: $order->orderClass,
                ticketTypeIds: $ticketTypeIds,
            );

            foreach ($registrationIds as $registrationId) {
                $existingPassIds = $this->registrations->passIdsForRegistration($registrationId);
                $passIds = $this->issuePasses->handle($registrationId);
                $newPassIds = array_values(array_diff($passIds, $existingPassIds));

                $stepData['pass_ids'] = array_values(array_unique([
                    ...$this->stringList($stepData['pass_ids']),
                    ...$newPassIds,
                ]));

                $session->setStepData($this->getIdentifier(), $stepData);

                $this->ticketing->deliverPasses($passIds);
                $issued += count($passIds);
            }
        }

        if ($issued === 0) {
            return $this->skipped('No registrations to issue passes for.');
        }

        return $this->success(
            sprintf('%d passes issued.', $issued),
            $stepData,
        );
    }

    public function compensate(CheckoutSession $session): StepResult
    {
        $stepData = $session->getStepData($this->getIdentifier());
        $passIds = $this->stringList($stepData['pass_ids'] ?? []);

        $revoked = $this->ticketing->revokePassesByIds($passIds, 'Checkout compensation');

        return $this->compensated(
            'Event passes compensated.',
            [
                'pass_ids' => $passIds,
                'revoked' => $revoked,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $value),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
