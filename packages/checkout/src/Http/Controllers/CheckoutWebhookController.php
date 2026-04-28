<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookProcessor;

final class CheckoutWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookConfig $config): JsonResponse
    {
        $response = (new WebhookProcessor($request, $config))->process();

        /** @var JsonResponse $response */
        return $response;
    }
}
