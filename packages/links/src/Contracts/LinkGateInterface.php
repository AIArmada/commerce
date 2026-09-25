<?php

declare(strict_types=1);

namespace AIArmada\Links\Contracts;

use AIArmada\Links\Models\Link;

interface LinkGateInterface
{
    /**
     * Consumer policy check for a redirect. Null when the link may redirect.
     *
     * @return string|null Machine-readable block reason (e.g. 'offer_inactive').
     */
    public function blockedReason(Link $link): ?string;
}
