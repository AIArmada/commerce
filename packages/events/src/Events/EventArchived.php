<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EventArchived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Event $event,
        public readonly ?Model $actor = null,
        public readonly ?string $note = null,
    ) {}
}
