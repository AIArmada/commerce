<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\RecipientRouting\Queries;

use AIArmada\Communications\Features\RecipientRouting\Models\CommunicationPreference;
use AIArmada\Communications\Runtime\PreferenceAuditModel;

final readonly class PreferenceAuditModelResolver implements PreferenceAuditModel
{
    public function preference(): string
    {
        return CommunicationPreference::class;
    }
}
