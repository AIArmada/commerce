<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventEngagementManager
{
    public function follow(mixed $actor, mixed $eventTarget, array $options = []): mixed;

    public function bookmark(mixed $actor, mixed $eventTarget, array $options = []): mixed;

    public function respond(mixed $actor, mixed $eventTarget, string $responseType, array $options = []): mixed;

    public function subscribe(mixed $actor, mixed $eventTarget = null, array $options = []): mixed;

    public function remind(mixed $actor, mixed $eventTarget, array $options = []): mixed;

    public function share(mixed $actor, mixed $eventTarget, array $options = []): mixed;

    public function stateFor(mixed $actor, mixed $eventTarget): array;
}
