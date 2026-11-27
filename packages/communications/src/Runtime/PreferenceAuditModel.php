<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

use AIArmada\Communications\Features\RecipientRouting\Models\CommunicationPreference;

/** Internal Eloquent relation and audit composition. */
interface PreferenceAuditModel
{
    /** @return class-string<CommunicationPreference> */
    public function preference(): string;
}
