<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Actions\Affiliates\AutoApproveAffiliateFromConversion;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;

final readonly class AutoApproveOpenAffiliate
{
    public function __construct(private AutoApproveAffiliateFromConversion $approve) {}

    public function handle(AffiliateConversionRecorded $event): void
    {
        $this->approve->execute($event->conversion->id);
    }
}
