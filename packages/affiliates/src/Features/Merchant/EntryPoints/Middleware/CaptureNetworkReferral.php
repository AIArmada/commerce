<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Merchant\EntryPoints\Middleware;

use AIArmada\Affiliates\Features\Merchant\Actions\CaptureMerchantReferral;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CaptureNetworkReferral
{
    public function __construct(private CaptureMerchantReferral $capture) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $this->capture->execute($request->session(), $request->query());
        }

        return $next($request);
    }
}
