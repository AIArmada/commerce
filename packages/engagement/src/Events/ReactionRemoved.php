<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Reaction;

final class ReactionRemoved
{
    public function __construct(public Reaction $reaction) {}
}
