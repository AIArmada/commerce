<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use Carbon\CarbonImmutable;

final class ArchiveOffer
{
    public function __construct(
        private readonly UpdateOffer $updateOffer,
    ) {}

    public function execute(AffiliateOffer $offer): AffiliateOffer
    {
        return $this->updateOffer->execute($offer, [
            'status' => OfferStatus::Archived,
            'archived_at' => CarbonImmutable::now(),
        ]);
    }
}
