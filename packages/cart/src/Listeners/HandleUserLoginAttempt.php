<?php

declare(strict_types=1);

namespace AIArmada\Cart\Listeners;

use AIArmada\Cart\Support\LoginMigrationIdentifierResolver;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Auth;

final class HandleUserLoginAttempt
{
    public function __construct(
        private readonly LoginMigrationIdentifierResolver $identifierResolver,
    ) {}

    public function handle(Attempting $event): void
    {
        if (! Auth::check()) {
            $currentSessionId = session()->getId();
            $identifiers = $this->identifierResolver->resolveFromCredentials($event->credentials);

            if ($identifiers !== [] && $currentSessionId) {
                $this->identifierResolver->cacheSessionForIdentifiers($identifiers, $currentSessionId);
            }
        }
    }
}
