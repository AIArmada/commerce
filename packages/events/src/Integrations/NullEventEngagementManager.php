<?php

declare(strict_types=1);

namespace AIArmada\Events\Integrations;

use AIArmada\Events\Contracts\EventEngagementManager;

final class NullEventEngagementManager implements EventEngagementManager
{
    public function follow(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        return null;
    }

    public function bookmark(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        return null;
    }

    public function respond(mixed $actor, mixed $eventTarget, string $responseType, array $options = []): mixed
    {
        return null;
    }

    public function subscribe(mixed $actor, mixed $eventTarget = null, array $options = []): mixed
    {
        return null;
    }

    public function remind(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        return null;
    }

    public function share(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        return null;
    }

    public function stateFor(mixed $actor, mixed $eventTarget): array
    {
        return [];
    }
}
