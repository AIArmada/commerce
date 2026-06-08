<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventAsset;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventReferenceAssignment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface EventRelationalContentSubject
{
    /**
     * @return MorphMany<EventClassification, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function classifications(): MorphMany;

    /**
     * @return MorphMany<EventAsset, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function assets(): MorphMany;

    /**
     * @return MorphMany<EventReferenceAssignment, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function references(): MorphMany;
}
