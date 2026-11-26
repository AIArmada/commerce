<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\AffiliateWebhookRequest;
use AIArmada\Affiliates\Data\AffiliateWebhookResult;

interface AffiliateWebhookTransport
{
    public function send(AffiliateWebhookRequest $request): AffiliateWebhookResult;
}
