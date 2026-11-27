<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\EntryPoints\Webhooks;

use AIArmada\CommerceSupport\Features\Webhooks\EntryPoints\CommerceWebhookProfile;
use AIArmada\Jnt\Features\Tracking\Support\WebhookPayload;
use Illuminate\Http\Request;

/**
 * Profile for determining if J&T webhooks should be processed.
 */
class JntWebhookProfile extends CommerceWebhookProfile
{
    /**
     * Determine if the request should be processed.
     */
    public function shouldProcess(Request $request): bool
    {
        $bizContent = $request->input('bizContent');

        if (! is_string($bizContent) || $bizContent === '') {
            return false;
        }

        WebhookPayload::decode($bizContent);

        return true;
    }
}
