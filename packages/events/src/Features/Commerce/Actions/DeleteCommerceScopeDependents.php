<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventSeatingDriver;
use AIArmada\Events\Contracts\EventTicketingDriver;
use Illuminate\Support\Facades\DB;

final class DeleteCommerceScopeDependents
{
    public function __construct(
        private readonly EventTicketingDriver $ticketing,
        private readonly EventSeatingDriver $seating,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        DB::transaction(function () use ($scopeType, $scopeId, $morphClass): void {
            $this->ticketing->deleteScopeChildren($scopeType, $scopeId, $morphClass);
            $this->seating->deleteScopeChildren($scopeType, $scopeId, $morphClass);
        });
    }
}
