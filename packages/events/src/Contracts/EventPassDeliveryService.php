<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventPass;

interface EventPassDeliveryService
{
    /**
     * Deliver a ticket/pass to its recipient.
     *
     * Implementations should handle channel-specific delivery
     * (email, download link, wallet pass, etc.) and may queue
     * the work internally.
     */
    public function deliver(EventPass $pass): void;
}
