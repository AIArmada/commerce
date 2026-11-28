<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\EntryPoints\Jobs;

use AIArmada\Docs\Features\Delivery\Actions\DeliverDocReminders;
use AIArmada\Docs\Features\Delivery\Queries\DueDocReminders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendDocReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ?string $docId = null,
        public int $daysBeforeDue = 3,
        public int $daysAfterOverdue = 1,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
        public bool $ownerBatch = false,
    ) {}

    public function handle(DeliverDocReminders $deliverDocReminders, DueDocReminders $dueDocReminders): void
    {
        if ($this->shouldFanOutByOwner()) {
            $this->dispatchPerOwner($dueDocReminders);

            return;
        }

        $deliverDocReminders->execute(
            $this->docId,
            $this->daysBeforeDue,
            $this->daysAfterOverdue,
            $this->ownerType,
            $this->ownerId,
        );
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'docs',
            'reminder',
            $this->docId ? "doc:{$this->docId}" : 'batch',
        ];
    }

    private function shouldFanOutByOwner(): bool
    {
        if ($this->docId !== null || $this->ownerBatch) {
            return false;
        }

        if (! config('docs.owner.enabled', false)) {
            return false;
        }

        return $this->ownerType === null && $this->ownerId === null;
    }

    private function dispatchPerOwner(DueDocReminders $dueDocReminders): void
    {
        $owners = $dueDocReminders->ownerTuples();

        if ($owners->isEmpty()) {
            return;
        }

        foreach ($owners as $ownerTuple) {
            $ownerType = $ownerTuple->owner_type;
            $ownerId = $ownerTuple->owner_id;
            self::dispatch(
                docId: null,
                daysBeforeDue: $this->daysBeforeDue,
                daysAfterOverdue: $this->daysAfterOverdue,
                ownerType: $ownerType,
                ownerId: $ownerId,
                ownerBatch: true,
            );
        }
    }
}
