<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Concerns;

use AIArmada\Engagement\Features\Interactions\Actions\ManageReactionsAction;
use AIArmada\Engagement\Features\Interactions\Models\Reaction;
use AIArmada\Engagement\Traits\InteractsWithEngagement;
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
        return app(ManageReactionsAction::class)->react($this->engagementActor(), $subject, $type, $options);
    }

    public function removeReaction(mixed $subject, ?string $type = null): void
    {
        app(ManageReactionsAction::class)->removeReaction($this->engagementActor(), $subject, $type);
    }
}
