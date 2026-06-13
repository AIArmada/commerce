<?php

declare(strict_types=1);

namespace AIArmada\Events\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\AbstractCheckoutStep;
use AIArmada\Events\Contracts\EventPassDeliveryService;
use AIArmada\Events\Contracts\EventPassIssuer;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use Illuminate\Database\Eloquent\Model;

final class IssueEventPassesStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly EventPassIssuer $passIssuer,
        private readonly EventPassDeliveryService $passDelivery,
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

        $orderClass = CommerceIntegration::requireModelClass('order_model', 'pass issuance');

        /** @var Model|null $order */
        $order = $orderClass::query()
            ->with('items.purchasable')
            ->find($session->order_id);

        if ($order === null) {
            return $this->skipped('Order not found.');
        }

        $issued = 0;
        $orderItems = $order->getRelation('items');
        $ticketTypeIds = $orderItems
            ->map(function (mixed $orderItem): ?string {
                $purchasable = $orderItem->getRelation('purchasable');

                if (! $purchasable instanceof EventTicketType) {
                    return null;
                }

                return $purchasable->getKey();
            })
            ->filter(static fn (?string $ticketTypeId): bool => $ticketTypeId !== null)
            ->unique()
            ->values();

        foreach ($ticketTypeIds as $ticketTypeId) {
            $purchasable = $orderItems
                ->first(function (mixed $orderItem) use ($ticketTypeId): bool {
                    $purchasable = $orderItem->getRelation('purchasable');

                    return $purchasable instanceof EventTicketType
                        && $purchasable->getKey() === $ticketTypeId;
                })?->getRelation('purchasable');

            if (! $purchasable instanceof EventTicketType) {
                continue;
            }

            $registrations = EventRegistration::byOrder($order)
                ->whereHas(
                    'items',
                    fn ($query) => $query
                        ->where('event_ticket_type_id', $purchasable->getKey()),
                )
                ->get();

            foreach ($registrations as $registration) {
                foreach ($this->passIssuer->issuePassesFor($registration) as $pass) {
                    $this->passDelivery->deliver($pass);
                    $issued++;
                }
            }
        }

        if ($issued === 0) {
            return $this->skipped('No registrations to issue passes for.');
        }

        return $this->success(sprintf('%d passes issued.', $issued));
    }
}
