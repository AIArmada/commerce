<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\EntryPoints\Listeners;

use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Features\Identity\Actions\TransitionAffiliateStatus;

final readonly class AutoApproveOpenAffiliate
{
    public function __construct(private TransitionAffiliateStatus $approve) {}

    public function handle(AffiliateConversionRecorded $event): void
    {
        $this->approve->approveFromConversion($event->conversion->id);
    }
}
