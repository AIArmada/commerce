<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Illuminate\Database\Eloquent\Model;

/**
 * Internal actor-side implementation shared by the public Can* traits.
 *
 * @mixin Model
 */
trait InteractsWithEngagement
{
    protected function engagementActor(): CanInteract
    {
        return EngagementModelGuard::requireContract($this, CanInteract::class, 'actor');
    }
}
