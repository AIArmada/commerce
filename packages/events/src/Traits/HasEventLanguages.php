<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventLanguage;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventLanguages
{
    /**
     * @return MorphMany<EventLanguage, $this>
     */
    public function languages(): MorphMany
    {
        return $this->morphMany(EventLanguage::class, 'languageable');
    }
}
