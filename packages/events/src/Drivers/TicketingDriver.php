<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Data\EventPassIssuanceData;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Ticketing\Actions\EnsureTicketTypeAction;
use AIArmada\Ticketing\Actions\IssuePassesAction;
use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class TicketingDriver implements EventTicketingDriver
{
    public function __construct(
        private readonly EnsureTicketTypeAction $ensureTicketType,
        private readonly IssuePassesAction $issuePasses,
        private readonly InventoryService $inventory,
        private readonly ?PassDeliveryServiceInterface $passDelivery = null,
    ) {}

    public function ensureTicketType(string $targetType, string $targetId, array $attributes = []): string
    {
        $quota = $attributes['quota'] ?? null;
        unset($attributes['quota']);

        $ticketType = $this->ensureTicketType->handle($this->scopeModel($targetType, $targetId), $attributes);

        if (is_numeric($quota) && (int) $quota > 0) {
            $this->syncQuota((string) $ticketType->getKey(), (int) $quota);
        }

        return (string) $ticketType->getKey();
    }

    public function syncQuota(string $ticketTypeId, int $quantity): void
    {
        $ticketType = TicketType::query()->findOrFail($ticketTypeId);
        $location = InventoryLocation::getOrCreateDefault();

        InventoryLevel::updateOrCreate(
            [
                'inventoryable_type' => $ticketType->getMorphClass(),
                'inventoryable_id' => $ticketType->getKey(),
                'location_id' => $location->getKey(),
            ],
            [
                'quantity_on_hand' => $quantity,
            ],
        );
    }

    public function issuePasses(EventPassIssuanceData $issuance): array
    {
        $ticketType = TicketType::query()->findOrFail($issuance->ticketTypeId);

        $passes = $this->issuePasses->handle(new PassIssuanceContext(
            ticketType: $ticketType,
            quantity: max(1, $issuance->quantity),
            holderAttributes: $issuance->holderAttributes,
            metadata: $issuance->metadata,
            registrationType: $issuance->registrationType,
            registrationId: $issuance->registrationId,
            occurrenceId: $issuance->occurrenceId,
            sessionId: $issuance->sessionId,
        ));

        return $passes
            ->map(static fn (Pass $pass): string => (string) $pass->getKey())
            ->values()
            ->all();
    }

    public function revokePasses(string $registrationType, string $registrationId, ?string $reason = null): int
    {
        $passes = Pass::query()
            ->where('registration_type', $registrationType)
            ->where('registration_id', $registrationId)
            ->get();

        $revoked = 0;

        foreach ($passes as $pass) {
            if ($pass->isValid()) {
                $pass->markRevoked($reason ?? 'Registration terminal');
                $revoked++;
            }
        }

        return $revoked;
    }

    public function revokePassesByIds(array $passIds, string $reason): int
    {
        if ($passIds === []) {
            return 0;
        }

        $passes = Pass::query()->whereKey($passIds)->get();
        $revoked = 0;

        foreach ($passes as $pass) {
            if (! $pass->isValid()) {
                continue;
            }

            $pass->markRevoked($reason);
            $revoked++;
        }

        return $revoked;
    }

    public function countPassesForRegistrationTicket(string $registrationType, string $registrationId, string $ticketTypeId): int
    {
        return Pass::query()
            ->where('registration_type', $registrationType)
            ->where('registration_id', $registrationId)
            ->where('ticket_type_id', $ticketTypeId)
            ->count();
    }

    public function deliverPasses(array $passIds): void
    {
        if ($passIds === []) {
            return;
        }

        $delivery = $this->passDelivery;

        if ($delivery === null) {
            return;
        }

        $passes = Pass::query()->whereKey($passIds)->get();

        foreach ($passes as $pass) {
            $delivery->deliver($pass);
        }
    }

    public function shipInventory(string $ticketTypeId, int $quantity, string $reason, ?string $reference = null): void
    {
        if ($quantity < 1) {
            return;
        }

        $ticketType = TicketType::query()->findOrFail($ticketTypeId);

        $this->inventory->shipFromDefault($ticketType, $quantity, $reason, $reference);
    }

    public function restoreRefundedRegistrationItemInventory(
        string $orderId,
        ?string $orderNumber,
        string $registrationItemId,
        string $ticketTypeId,
        int $quantity,
    ): void {
        if ($quantity < 1) {
            return;
        }

        $reason = 'event_registration_item_refunded:' . $registrationItemId;
        $note = sprintf('Restored from refunded event registration item %s', $registrationItemId);

        $ticketType = TicketType::query()->findOrFail($ticketTypeId);
        $morphType = $ticketType->getMorphClass();

        $restored = (int) InventoryMovement::query()
            ->where('type', MovementType::Receipt->value)
            ->where('reason', $reason)
            ->where('inventoryable_type', $morphType)
            ->where('inventoryable_id', $ticketType->getKey())
            ->sum('quantity');

        $remaining = max(0, $quantity - $restored);

        if ($remaining === 0) {
            return;
        }

        $restoredByLocation = InventoryMovement::query()
            ->where('type', MovementType::Receipt->value)
            ->where('reason', $reason)
            ->where('inventoryable_type', $morphType)
            ->where('inventoryable_id', $ticketType->getKey())
            ->whereNotNull('to_location_id')
            ->selectRaw('to_location_id, SUM(quantity) as restored_quantity')
            ->groupBy('to_location_id')
            ->pluck('restored_quantity', 'to_location_id')
            ->all();

        $references = array_values(array_filter(
            [$orderId, $orderNumber],
            static fn (?string $value): bool => $value !== null && $value !== '',
        ));

        $shipments = InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->whereIn('reference', $references)
            ->where('inventoryable_type', $morphType)
            ->where('inventoryable_id', $ticketType->getKey())
            ->orderBy('created_at')
            ->get();

        foreach ($shipments as $shipment) {
            if ($remaining === 0 || ! $shipment instanceof InventoryMovement || $shipment->from_location_id === null) {
                continue;
            }

            $locationId = (string) $shipment->from_location_id;
            $alreadyRestored = (int) ($restoredByLocation[$locationId] ?? 0);
            $restorable = min($remaining, max(0, (int) $shipment->quantity - $alreadyRestored));

            if ($restorable < 1) {
                continue;
            }

            $this->inventory->receive(
                model: $ticketType,
                locationId: $locationId,
                quantity: $restorable,
                reason: $reason,
                note: $note,
            );

            $restoredByLocation[$locationId] = $alreadyRestored + $restorable;
            $remaining -= $restorable;
        }

        if ($remaining > 0) {
            Log::warning('Could not restore all ticket inventory for the refunded registration item.', [
                'order_id' => $orderId,
                'ticket_type_id' => $ticketTypeId,
                'quantity_remaining' => $remaining,
                'registration_item_id' => $registrationItemId,
            ]);
        }
    }

    public function deleteScopeChildren(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        if ($scopeType === 'registration') {
            $registrationClass = ModelResolver::registrationClass();
            $type = $morphClass ?? (new $registrationClass)->getMorphClass();

            Pass::query()
                ->where('registration_type', $type)
                ->where('registration_id', $scopeId)
                ->chunkById(200, function (iterable $passes): void {
                    foreach ($passes as $pass) {
                        $pass->delete();
                    }
                });

            return;
        }

        $modelClass = $this->scopeModelClass($scopeType);
        $type = $morphClass ?? (new $modelClass)->getMorphClass();

        TicketType::query()
            ->where('ticketable_type', $type)
            ->where('ticketable_id', $scopeId)
            ->chunkById(200, function (iterable $ticketTypes): void {
                foreach ($ticketTypes as $ticketType) {
                    $ticketType->delete();
                }
            });

        Pass::query()
            ->where('ticketable_type', $type)
            ->where('ticketable_id', $scopeId)
            ->chunkById(200, function (iterable $passes): void {
                foreach ($passes as $pass) {
                    $pass->delete();
                }
            });
    }

    private function scopeModel(string $scopeType, string $scopeId): Model
    {
        $modelClass = $this->scopeModelClass($scopeType);

        $model = $modelClass::query()->findOrFail($scopeId);

        return $model;
    }

    /**
     * @return class-string<Model>
     */
    private function scopeModelClass(string $scopeType): string
    {
        return match ($scopeType) {
            'event' => ModelResolver::eventClass(),
            'occurrence' => ModelResolver::occurrenceClass(),
            'session' => ModelResolver::sessionClass(),
            default => throw new InvalidArgumentException(sprintf('Unsupported event scope type [%s].', $scopeType)),
        };
    }
}
