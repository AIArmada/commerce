<?php

declare(strict_types=1);

namespace AIArmada\Events\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\AbstractCheckoutStep;
use AIArmada\Events\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class IssueEventPassesStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly IssueEventRegistrationPassesAction $issuePasses,
        private readonly PassDeliveryServiceInterface $passDelivery,
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
        $stepData = $session->getStepData($this->getIdentifier());
        $stepData['pass_ids'] = $this->stringList($stepData['pass_ids'] ?? []);
        $orderItems = $order->getRelation('items');
        $ticketTypeIds = $orderItems
            ->map(function (mixed $orderItem): ?string {
                $purchasable = $orderItem->getRelation('purchasable');

                if (! $purchasable instanceof TicketType || EventTicketScope::target($purchasable) === null) {
                    return null;
                }

                return $purchasable->getKey();
            })
            ->filter(static fn (?string $ticketTypeId): bool => $ticketTypeId !== null)
            ->unique()
            ->values();

        if ($ticketTypeIds->isNotEmpty()) {
            $registrationClass = ModelResolver::registrationClass();
            $registrations = $registrationClass::byOrder($order)
                ->whereHas(
                    'items',
                    fn ($query) => $query->whereIn('ticket_type_id', $ticketTypeIds->all()),
                )
                ->with('items')
                ->get();

            foreach ($registrations as $registration) {
                $existingPassIds = $registration->passes()
                    ->pluck('id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->all();
                $passes = $this->issuePasses->handle($registration);
                $newPassIds = [];

                foreach ($passes as $pass) {
                    if (! $pass instanceof Pass) {
                        continue;
                    }

                    $passId = $pass->getKey();

                    if ($passId !== null && ! in_array((string) $passId, $existingPassIds, true)) {
                        $newPassIds[] = (string) $passId;
                    }
                }

                $stepData['pass_ids'] = array_values(array_unique([
                    ...$this->stringList($stepData['pass_ids']),
                    ...$newPassIds,
                ]));
                $session->setStepData($this->getIdentifier(), $stepData);

                foreach ($passes as $pass) {
                    $this->passDelivery->deliver($pass);
                    $issued++;
                }
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
        $errors = [];
        $revoked = 0;

        if ($passIds !== []) {
            $passes = Pass::query()->whereKey($passIds)->get();

            foreach ($passes as $pass) {
                if (! $pass->isValid()) {
                    continue;
                }

                try {
                    $pass->markRevoked('Checkout compensation');
                    $revoked++;
                } catch (Throwable $e) {
                    $errors[(string) $pass->getKey()] = $e->getMessage() !== ''
                        ? $e->getMessage()
                        : $e::class;
                }
            }
        }

        if ($errors !== []) {
            return $this->failed('Event pass compensation failed.', $errors);
        }

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
