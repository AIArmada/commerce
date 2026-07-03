<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\EventRegistration;
use AIArmada\Ticketing\Models\Pass;

class RevokePassesForRegistrationAction
{
    /**
     * @return int Number of passes revoked
     */
    public function handle(EventRegistration $registration, ?string $reason = null): int
    {
        $passes = Pass::query()
            ->where('registration_type', $registration->getMorphClass())
            ->where('registration_id', $registration->getKey())
            ->get();

        foreach ($passes as $pass) {
            if ($pass->isValid()) {
                $pass->markRevoked($reason ?? 'Registration terminal');
            }
        }

        return $passes->count();
    }
}
