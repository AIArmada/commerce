<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Policy;

use AIArmada\Events\Enums\EventModerationStatus;
use InvalidArgumentException;

final class EventModerationPolicy
{
    public static function actionKeys(): array
    {
        return array_keys(self::transitionRules());
    }

    public static function allowedActionsFor(EventModerationStatus $fromStatus): array
    {
        $current = $fromStatus->value;

        return array_values(array_filter(
            array_keys(self::transitionRules()),
            static function (string $actionKey) use ($current): bool {
                $rule = self::transitionRule($actionKey);

                return in_array($current, $rule['from'], true);
            },
        ));
    }

    public static function actionKeyForDecision(EventModerationStatus $decision): string
    {
        return match ($decision) {
            EventModerationStatus::Pending => 'submit',
            EventModerationStatus::ChangesRequested => 'request_changes',
            EventModerationStatus::Approved => 'approve',
            EventModerationStatus::Rejected => 'reject',
        };
    }

    public static function canTransition(string $actionKey, EventModerationStatus $fromStatus, EventModerationStatus $toStatus): bool
    {
        $rule = self::transitionRule($actionKey);

        return in_array($fromStatus->value, $rule['from'], true)
            && $toStatus->value === $rule['to'];
    }

    public static function noteRequired(string $actionKey): bool
    {
        $rule = self::transitionRule($actionKey);

        return (bool) ($rule['note_required'] ?? false);
    }

    public static function reasonRequired(string $actionKey): bool
    {
        $rule = self::transitionRule($actionKey);

        return (bool) ($rule['reason_required'] ?? false);
    }

    public static function hasReasonCode(string $reasonKey): bool
    {
        return array_key_exists($reasonKey, self::reasonCodes());
    }

    public static function reasonLabel(string $reasonKey): ?string
    {
        $reason = self::reasonCodes()[$reasonKey] ?? null;

        if (! is_array($reason)) {
            return null;
        }

        $label = $reason['label'] ?? null;

        return is_string($label) && mb_trim($label) !== ''
            ? mb_trim($label)
            : null;
    }

    public static function reasonCodes(): array
    {
        $configured = config('events.moderation.reason_codes', []);

        if (! is_array($configured) || $configured === []) {
            return self::defaultReasonCodes();
        }

        return $configured;
    }

    public static function defaultReasonCodes(): array
    {
        return [
            'approved_for_publish' => [
                'label' => 'Approved for Publish',
                'note_required' => false,
            ],
            'needs_more_information' => [
                'label' => 'Needs More Information',
                'note_required' => true,
            ],
            'policy_violation' => [
                'label' => 'Policy Violation',
                'note_required' => true,
            ],
            'duplicate' => [
                'label' => 'Duplicate',
                'note_required' => true,
            ],
        ];
    }

    private static function transitionRules(): array
    {
        $configured = config('events.moderation.actions', []);

        if (! is_array($configured) || $configured === []) {
            return self::defaultTransitionRules();
        }

        return $configured;
    }

    private static function defaultTransitionRules(): array
    {
        return [
            'submit' => [
                'from' => ['draft', 'pending', 'approved', 'changes_requested', 'rejected'],
                'to' => EventModerationStatus::Pending->value,
                'note_required' => false,
                'reason_required' => false,
            ],
            'approve' => [
                'from' => ['pending', 'changes_requested'],
                'to' => EventModerationStatus::Approved->value,
                'note_required' => false,
                'reason_required' => false,
            ],
            'request_changes' => [
                'from' => ['pending', 'approved'],
                'to' => EventModerationStatus::ChangesRequested->value,
                'note_required' => true,
                'reason_required' => true,
            ],
            'reject' => [
                'from' => ['pending', 'approved', 'changes_requested'],
                'to' => EventModerationStatus::Rejected->value,
                'note_required' => true,
                'reason_required' => true,
            ],
            'cancel' => [
                'from' => ['pending', 'approved', 'changes_requested', 'rejected'],
                'to' => EventModerationStatus::Pending->value,
                'note_required' => false,
                'reason_required' => false,
            ],
            'reconsider' => [
                'from' => ['rejected', 'changes_requested'],
                'to' => EventModerationStatus::Pending->value,
                'note_required' => false,
                'reason_required' => false,
            ],
            'revert_to_draft' => [
                'from' => ['pending', 'approved', 'changes_requested', 'rejected'],
                'to' => EventModerationStatus::Pending->value,
                'note_required' => false,
                'reason_required' => false,
            ],
            'remoderate' => [
                'from' => ['approved', 'changes_requested', 'rejected'],
                'to' => EventModerationStatus::Pending->value,
                'note_required' => false,
                'reason_required' => false,
            ],
        ];
    }

    private static function transitionRule(string $actionKey): array
    {
        $rules = self::transitionRules();

        if (! array_key_exists($actionKey, $rules)) {
            throw new InvalidArgumentException(sprintf('Unknown moderation action [%s].', $actionKey));
        }

        $rule = $rules[$actionKey];

        return [
            'from' => array_values(array_filter(
                array_map(
                    static fn (mixed $status): ?string => is_string($status) && mb_trim($status) !== ''
                        ? mb_trim($status)
                        : null,
                    is_array($rule['from'] ?? null) ? $rule['from'] : [],
                ),
            )),
            'to' => is_string($rule['to'] ?? null) && mb_trim((string) $rule['to']) !== ''
                ? mb_trim((string) $rule['to'])
                : EventModerationStatus::Pending->value,
            'note_required' => (bool) ($rule['note_required'] ?? false),
            'reason_required' => (bool) ($rule['reason_required'] ?? false),
        ];
    }
}
