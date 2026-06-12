<?php
declare(strict_types=1);
namespace AIArmada\Engagement\Traits;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Models\Share;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait CanShare
{
    /** @return MorphMany<Share, $this> */
    public function shares(): MorphMany
    {
        return $this->morphMany(Share::class, 'sharer');
    }

    public function share(mixed $subject, array $options = []): Share
    {
        return app(EngagementManager::class)->share($this, $subject, $options);
    }
}
