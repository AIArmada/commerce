<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Merchant;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures the network link code carried by deep-link redirects so the
 * merchant can report the eventual sale against it (see
 * NetworkPostbackClient).
 *
 * Register in the merchant app's `web` group. Query parameter and session
 * key are configurable via `affiliates.merchant`.
 */
final class CaptureNetworkReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $code = $request->query((string) config('affiliates.merchant.referral_param', 'anl'));

            if (is_string($code) && $code !== '') {
                $request->session()->put(
                    (string) config('affiliates.merchant.session_key', 'affiliate_network.link_code'),
                    $code
                );
            }
        }

        return $next($request);
    }
}
