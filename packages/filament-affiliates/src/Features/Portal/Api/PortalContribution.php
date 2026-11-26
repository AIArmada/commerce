<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Portal\Api;

interface PortalContribution
{
    /** @return class-string */
    public function registration(): string;

    /**
     * @param  array<string, bool>  $features
     * @return list<class-string>
     */
    public function pages(array $features): array;
}
