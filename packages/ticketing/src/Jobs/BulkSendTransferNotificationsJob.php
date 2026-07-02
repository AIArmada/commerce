<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Jobs;

use AIArmada\Ticketing\Events\PassesBulkTransferred;
use AIArmada\Ticketing\Notifications\PassTransferredToNewHolderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

final class BulkSendTransferNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly PassesBulkTransferred $event,
    ) {}

    public function handle(): void
    {
        foreach ($this->event->passes as $pass) {
            $holder = $pass->holder;

            if ($holder === null) {
                continue;
            }

            $transferredFrom = $this->event->toHolder ?? $holder;

            Notification::route('mail', $holder->email)
                ->notify(new PassTransferredToNewHolderNotification($pass, $transferredFrom, $this->event->reason));
        }
    }
}
