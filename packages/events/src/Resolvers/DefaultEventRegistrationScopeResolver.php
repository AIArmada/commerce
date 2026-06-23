<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Enums\RegistrationMode;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventRegistrationScope;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class DefaultEventRegistrationScopeResolver implements EventRegistrationScopeResolver
{
    public function resolve(Model $target): EventRegistrationScope
    {
        [$event, $occurrence, $session] = match (true) {
            $target instanceof EventSession => [$target->event, $target->occurrence, $target],
            $target instanceof EventOccurrence => [$target->event, $target, null],
            $target instanceof Event => [$target, null, null],
            default => throw new InvalidArgumentException(
                sprintf('Target must be Event, EventOccurrence, or EventSession; got %s.', $target::class),
            ),
        };

        return new EventRegistrationScope(
            event: $event,
            occurrence: $occurrence,
            session: $session,
            pricingMode: $this->resolvePricingMode($event, $occurrence, $session),
            registrationMode: $this->resolveRegistrationMode($event, $occurrence, $session),
            shouldIssuePasses: $this->resolveShouldIssuePasses($event, $occurrence, $session),
            capacity: $session?->capacity ?? $occurrence?->capacity,
        );
    }

    private function resolvePricingMode(Event $event, ?EventOccurrence $occurrence, ?EventSession $session): PricingMode
    {
        if ($session !== null) {
            return $session->effectivePricingMode();
        }

        if ($occurrence !== null) {
            return $occurrence->effectivePricingMode();
        }

        return $event->effectivePricingMode();
    }

    private function resolveRegistrationMode(Event $event, ?EventOccurrence $occurrence, ?EventSession $session): RegistrationMode
    {
        if ($session !== null) {
            return $session->effectiveRegistrationMode();
        }

        if ($occurrence !== null) {
            return $occurrence->effectiveRegistrationMode();
        }

        return $event->effectiveRegistrationMode();
    }

    private function resolveShouldIssuePasses(Event $event, ?EventOccurrence $occurrence, ?EventSession $session): bool
    {
        if ($session !== null) {
            return $session->shouldIssuePassesForFree();
        }

        if ($occurrence !== null) {
            return $occurrence->shouldIssuePassesForFree();
        }

        return $event->shouldIssuePassesForFree();
    }
}
