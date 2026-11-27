<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Features\Interactions\Models\Reaction;

final class ReactionCreated
{
    public function __construct(public Reaction $reaction) {}
}
