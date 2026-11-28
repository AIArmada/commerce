<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\EntryPoints\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Ticketing\Data\PassHolderData;
use AIArmada\Ticketing\Data\PassTransferData;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Models\PassHolder;
use AIArmada\Ticketing\Notifications\PassTransferredToNewHolderNotification;
use AIArmada\Ticketing\Notifications\PassTransferredToOldHolderNotification;
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

    /** @param list<PassTransferData> $transfers */
    public function __construct(
        public readonly array $transfers,
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
        $passIds = array_values(array_filter(array_map(
            static fn (PassTransferData $transfer): ?string => $transfer->pass->id,
            $this->transfers,
        )));

        if ($passIds === []) {
            return;
        }

        $passes = Pass::query()->whereKey($passIds)->get()->keyBy(fn (Pass $pass): string => (string) $pass->getKey());
        $currentHolders = PassHolder::query()
            ->whereIn('pass_id', $passIds)
            ->where('is_current', true)
            ->get()
            ->keyBy(fn (PassHolder $holder): string => (string) $holder->pass_id);

        foreach ($this->transfers as $transfer) {
            $passId = $transfer->pass->id;
            $pass = $passId === null ? null : $passes->get($passId);
            $currentHolder = $passId === null ? null : $currentHolders->get($passId);

            if (! $pass instanceof Pass || ! $currentHolder instanceof PassHolder) {
                continue;
            }

            if ((string) $currentHolder->getKey() !== $transfer->newHolder->id) {
                continue;
            }

            if (! blank($transfer->previousHolder->email) && $transfer->previousHolder->id !== $transfer->newHolder->id) {
                Notification::route('mail', $transfer->previousHolder->email)
                    ->notify(new PassTransferredToOldHolderNotification(
                        $transfer->pass,
                        $transfer->newHolder,
                        $transfer->reason,
                    ));
            }

            if (! blank($transfer->newHolder->email)) {
                Notification::route('mail', $transfer->newHolder->email)
                    ->notify(new PassTransferredToNewHolderNotification(
                        $transfer->pass,
                        $this->previousHolderData($transfer->previousHolder, $transfer->newHolder),
                        $transfer->reason,
                    ));
            }
        }
    }

    private function previousHolderData(PassHolderData $previous, PassHolderData $new): PassHolderData
    {
        return $previous->id === $new->id ? $new : $previous;
    }
}
