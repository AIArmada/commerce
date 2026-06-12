<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventEligibilityRule;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventEligibilityRules
{
    /**
     * @return MorphMany<EventEligibilityRule, $this>
     */
    public function eligibilityRules(): MorphMany
    {
        return $this->morphMany(EventEligibilityRule::class, 'eligible');
    }
}
