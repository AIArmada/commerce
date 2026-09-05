<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Ticketing\Events\PassesBulkTransferred;
use AIArmada\Ticketing\Notifications\PassTransferredToNewHolderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

final class BulkSendTransferNotificationsJob implements OwnerScopedJob, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    public function __construct(
        public readonly PassesBulkTransferred $event,
        public readonly ?string $ownerType,
        public readonly string | int | null $ownerId,
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
        foreach ($this->event->passes as $pass) {
            $holder = $pass->holder;

            if ($holder === null || blank($holder->email)) {
                continue;
            }

            $transferredFrom = $this->event->toHolder ?? $holder;

            Notification::route('mail', $holder->email)
                ->notify(new PassTransferredToNewHolderNotification($pass, $transferredFrom, $this->event->reason));
        }
    }
}
