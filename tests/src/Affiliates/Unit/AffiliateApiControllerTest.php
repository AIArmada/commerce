<?php

declare(strict_types=1);

use AIArmada\Affiliates\Http\Controllers\AffiliateApiController;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;

test('AffiliateApiController can be instantiated', function (): void {
    $affiliateService = app(AffiliateService::class);
    $reportService = app(AffiliateReportService::class);
    $linkGenerator = app(AffiliateLinkGenerator::class);

    $controller = new AffiliateApiController(
        $affiliateService,
        $reportService,
        $linkGenerator
    );

    expect($controller)->toBeInstanceOf(AffiliateApiController::class);
});
