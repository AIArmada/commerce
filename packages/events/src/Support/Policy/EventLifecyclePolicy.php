<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Policy;

use AIArmada\Events\Enums\EventStatus;
use InvalidArgumentException;

final class EventLifecyclePolicy
{
    /**
     * @return list<string>
     */
    public static function actionKeys(): array
    {
        return array_keys(self::transitionRules());
    }

    /**
     * @return list<string>
     */
    public static function allowedActionsFor(EventStatus $fromStatus): array
    {
        return array_values(array_filter(
            array_keys(self::transitionRules()),
            static function (string $actionKey) use ($fromStatus): bool {
                $rule = self::transitionRule($actionKey);

                return in_array($fromStatus, $rule['from'], true);
            },
        ));
    }

    public static function canTransition(string $actionKey, EventStatus $fromStatus, EventStatus $toStatus): bool
    {
        $rule = self::transitionRule($actionKey);

        return in_array($fromStatus, $rule['from'], true)
            && $toStatus === $rule['to'];
    }

    public static function noteRequired(string $actionKey): bool
    {
        $rule = self::transitionRule($actionKey);

        return (bool) ($rule['note_required'] ?? false);
    }

    public static function targetStatusFor(string $actionKey): ?EventStatus
    {
        $rule = self::transitionRules()[$actionKey] ?? null;

        if ($rule === null) {
            return null;
        }

        return $rule['to'];
    }

    /**
     * @return array<string, array{from: list<EventStatus>, to: EventStatus, note_required: bool}>
     */
    public static function transitionRules(): array
    {
        $configured = config('events.lifecycle.actions', []);

        if (! is_array($configured) || $configured === []) {
            return self::defaultTransitionRules();
        }

        return $configured;
    }

    /**
     * @return array<string, array{from: list<EventStatus>, to: EventStatus, note_required: bool}>
     */
    public static function defaultTransitionRules(): array
    {
        return [
            'cancel' => [
                'from' => [EventStatus::Active],
                'to' => EventStatus::Cancelled,
                'note_required' => false,
            ],
        ];
    }

    /**
     * @return array{from: list<EventStatus>, to: EventStatus, note_required: bool}
     */
    private static function transitionRule(string $actionKey): array
    {
        $rules = self::transitionRules();

        if (! array_key_exists($actionKey, $rules)) {
            throw new InvalidArgumentException(sprintf('Unknown lifecycle action [%s].', $actionKey));
        }

        return [
            'from' => $rules[$actionKey]['from'],
            'to' => $rules[$actionKey]['to'],
            'note_required' => $rules[$actionKey]['note_required'],
        ];
    }
}
