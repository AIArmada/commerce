<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers;

use AIArmada\Affiliates\Actions\Affiliates\CapturePublicAffiliateReferral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class PublicAffiliateReferralController extends Controller
{
    public function __invoke(
        Request $request,
        string $affiliateCode,
        CapturePublicAffiliateReferral $capturePublicAffiliateReferral,
    ): RedirectResponse {
        return $capturePublicAffiliateReferral->handle($request, $affiliateCode);
    }
}
