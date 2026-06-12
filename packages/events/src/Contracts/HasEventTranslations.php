<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface HasEventTranslations
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany|\Illuminate\Support\Collection
     */
    public function eventTranslations(): mixed;
}
