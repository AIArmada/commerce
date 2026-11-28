<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Services;

use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Queries\MapPassData;
use AIArmada\Ticketing\Notifications\TicketNotification;
use Illuminate\Support\Facades\Notification;

final readonly class DefaultPassDeliveryService implements PassDeliveryServiceInterface
{
    public function __construct(private MapPassData $mapPassData) {}

    public function deliver(string $passId): void
    {
        $this->deliverMany([$passId]);
    }

    public function deliverMany(array $passIds): void
    {
        if (! config('ticketing.notifications.ticket.enabled', true) || $passIds === []) {
            return;
        }

        $passes = Pass::query()->with(['holder', 'ticketType'])->whereKey($passIds)->get();

        foreach ($passes as $pass) {
            $holder = $pass->holder;

            if ($holder === null || blank($holder->email)) {
                continue;
            }

            Notification::route('mail', $holder->email)
                ->notify(new TicketNotification($this->mapPassData->handle($pass)));
        }
    }
}
