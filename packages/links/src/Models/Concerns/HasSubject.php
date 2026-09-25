<?php

declare(strict_types=1);

namespace AIArmada\Links\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasSubject
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey());
    }
}
