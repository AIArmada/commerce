<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventEngagementManager
{
    public function follow(mixed $actor, mixed $eventTarget, array $options = []): ?array;

    public function bookmark(mixed $actor, mixed $eventTarget, array $options = []): ?array;

    public function respond(mixed $actor, mixed $eventTarget, string $responseType, array $options = []): ?array;

    public function subscribe(mixed $actor, mixed $eventTarget = null, array $options = []): ?array;

    public function remind(mixed $actor, mixed $eventTarget, array $options = []): ?array;

    public function share(mixed $actor, mixed $eventTarget, array $options = []): ?array;

    public function stateFor(mixed $actor, mixed $eventTarget): array;
}
