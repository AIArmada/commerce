<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanReact
{
    use InteractsWithEngagement;

    /**
     * @return MorphMany<Reaction, $this>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactor');
    }

    public function react(mixed $subject, string $type, array $options = []): Reaction
    {
        return $this->engagementManager()->react($this->engagementActor(), $subject, $type, $options);
    }

    public function removeReaction(mixed $subject, ?string $type = null): void
    {
        $this->engagementManager()->removeReaction($this->engagementActor(), $subject, $type);
    }
}
