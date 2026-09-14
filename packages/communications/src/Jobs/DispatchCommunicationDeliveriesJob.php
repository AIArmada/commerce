<?php

declare(strict_types=1);

namespace AIArmada\Communications\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Communications\Actions\RecalculateCommunicationStatusAction;
use AIArmada\Communications\Actions\TransitionDeliveryAction;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\CommunicationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class DispatchCommunicationDeliveriesJob implements OwnerScopedJob, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    public function __construct(
        public readonly string $communicationId,
        public readonly ?string $ownerType = null,
        public readonly string | int | null $ownerId = null,
        public readonly bool $ownerIsGlobal = false,
    ) {}

    public function ownerContext(): OwnerJobContext
    {
        return new OwnerJobContext(
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
            ownerIsGlobal: $this->ownerIsGlobal,
        );
    }

    protected function performJob(): void
    {
        $transitioned = 0;

        CommunicationDelivery::query()
            ->where('communication_id', $this->communicationId)
            ->where('status', DeliveryStatus::Queued)
            ->orderBy('queued_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lock('for update skip locked')
            ->chunkById(200, function ($deliveries) use (&$transitioned): void {
                foreach ($deliveries as $delivery) {
                    app(TransitionDeliveryAction::class)->handle($delivery, DeliveryStatus::Sending);
                    $transitioned++;
                }
            });

        if ($transitioned > 0) {
            app(RecalculateCommunicationStatusAction::class)->handle($this->communicationId);
        }
    }
}
