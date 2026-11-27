<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/** Internal Eloquent relation and audit composition. */
interface PreferenceAuditModel
{
    /** @return class-string<\AIArmada\Communications\Features\RecipientRouting\Models\CommunicationPreference> */
    public function preference(): string;
}
