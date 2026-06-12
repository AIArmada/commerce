<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

interface HasEventTranslations
{
    /**
     * @return MorphMany|Collection
     */
    public function eventTranslations(): mixed;
}
