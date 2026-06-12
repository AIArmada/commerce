<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventInteractionManager
{
    public function isFollowing(mixed $actor, mixed $target): bool;

    public function follow(mixed $actor, mixed $target, array $options = []): void;

    public function unfollow(mixed $actor, mixed $target, array $options = []): void;

    public function isBookmarked(mixed $actor, mixed $target): bool;

    public function bookmark(mixed $actor, mixed $target, array $options = []): void;

    public function removeBookmark(mixed $actor, mixed $target, array $options = []): void;

    public function responseFor(mixed $actor, mixed $target): ?string;

    public function respond(mixed $actor, mixed $target, string $responseType, array $options = []): void;

    public function subscribe(mixed $actor, mixed $target = null, string $subscriptionType = 'updates', array $criteria = [], array $options = []): void;

    public function setReminder(mixed $actor, mixed $target, string $reminderType, array $options = []): void;
}
