<?php
declare(strict_types=1);
namespace AIArmada\Engagement\Traits;
use AIArmada\Engagement\Models\Share;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasShares
{
    /** @return MorphMany<Share, $this> */
    public function shares(): MorphMany
    {
        return $this->morphMany(Share::class, 'shareable');
    }

    /** @return MorphMany<Share, $this> */
    public function successfulShares(): MorphMany
    {
        return $this->shares()->where('status', 'shared');
    }
}
