<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Share;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasShares
{
    use ReceivesEngagement;

    /** @return MorphMany<Share, $this> */
    public function shares(): MorphMany
    {
        return $this->engagementRelation(Share::class, 'shareable');
    }

    /** @return MorphMany<Share, $this> */
    public function successfulShares(): MorphMany
    {
        return $this->engagementRelation(Share::class, 'shareable')->where('status', 'shared');
    }
}
