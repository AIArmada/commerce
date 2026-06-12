<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventRegistration;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventRegistrations
{
    /**
     * @return MorphMany<EventRegistration, $this>
     */
    public function registrations(): MorphMany
    {
        return $this->morphMany(EventRegistration::class, 'registrant');
    }
}
