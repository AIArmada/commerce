<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;
use Illuminate\Http\Request;

/**
 * Profile for determining if CHIP webhooks should be processed.
 */
class ChipWebhookProfile extends CommerceWebhookProfile
{
    /**
     * Determine if the request should be processed.
     */
    public function shouldProcess(Request $request): bool
    {
        $eventType = $request->input('event_type') ?? $request->input('event');

        if (is_string($eventType) && $eventType !== '') {
            // Process all valid CHIP events
            $validPrefixes = [
                'purchase.',
                'payment.',
                'payout.',
                'billing_template_client.',
            ];

            foreach ($validPrefixes as $prefix) {
                if (str_starts_with($eventType, $prefix)) {
                    return true;
                }
            }

            return false;
        }

        $type = $request->input('type');
        $validTypes = [
            'purchase',
            'payment',
            'payout',
            'billing_template_client',
        ];

        return is_string($type) && in_array($type, $validTypes, true);
    }
}
