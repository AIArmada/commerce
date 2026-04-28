<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;
use Illuminate\Http\Request;

/**
 * Profile for determining if CashierChip webhooks should be processed.
 */
class CashierChipWebhookProfile extends CommerceWebhookProfile
{
    /**
     * Determine if the request should be processed.
     */
    public function shouldProcess(Request $request): bool
    {
        // Only process if event_type is present and starts with purchase.
        $eventType = $request->input('event_type');

        if (empty($eventType)) {
            return false;
        }

        // Process purchase-related events
        return str_starts_with($eventType, 'purchase.');
    }
}
