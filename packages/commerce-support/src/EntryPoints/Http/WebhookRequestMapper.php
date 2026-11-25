<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\EntryPoints\Http;

use AIArmada\CommerceSupport\Data\WebhookRequestData;
use Illuminate\Http\Request;

final class WebhookRequestMapper
{
    public static function fromRequest(Request $request): WebhookRequestData
    {
        return new WebhookRequestData($request->getContent(), $request->headers->all());
    }
}
