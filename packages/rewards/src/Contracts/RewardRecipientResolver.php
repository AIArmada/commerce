<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

/**
 * Payout/fulfillment identity boundary for polymorphic recipients.
 *
 * Core stores recipients as morph tuples and never queries a concrete host
 * model. Implementations resolve existence (and, for automated rails,
 * external recipient references) for a tuple. The default binding checks
 * morph-map existence only and creates no external account.
 */
interface RewardRecipientResolver
{
    /**
     * Whether the recipient tuple resolves to an existing record.
     */
    public function exists(string $recipientType, string $recipientId): bool;

    /**
     * Stable mutex segment for serializing payout operations per
     * recipient. Distinct recipients must yield distinct segments.
     */
    public function mutexKey(string $recipientType, string $recipientId): string;
}
