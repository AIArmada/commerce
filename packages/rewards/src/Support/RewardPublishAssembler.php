<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardProgramMode;
use InvalidArgumentException;

/**
 * Assemble publish rules/terms from structured operator input.
 *
 * Staff never hand-write rules JSON: the publish form collects typed
 * fields per enabled component plus eligibility/windows/caps/holds
 * controls and plain terms fields, and this assembler builds the exact
 * rules/terms maps the validator accepts. Unknown component toggles or
 * empty component sets are refused here, before the publish action.
 */
final class RewardPublishAssembler
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{rules: array<string, mixed>, terms: array<string, mixed>}
     */
    public static function assemble(RewardProgramMode $mode, array $data): array
    {
        $components = [];

        if (array_key_exists('component_rows', $data)) {
            $components = self::componentRows($mode, $data['component_rows']);
        } else {
            foreach (['recognition', 'points', 'badge', 'cash', 'benefit'] as $type) {
                if (! self::componentEnabled($mode, $type, $data)) {
                    continue;
                }

                $components[] = match ($type) {
                    'recognition' => array_filter([
                        'type' => 'recognition',
                        'template' => self::text($data['recognition_template'] ?? null, 64),
                    ], static fn (mixed $value): bool => $value !== null),
                    'points' => [
                        'type' => 'points',
                        'points' => self::int($data['points_amount'] ?? null, 'points amount'),
                        'unit' => self::requiredText($data['points_unit'] ?? null, 16, 'points unit'),
                    ],
                    'badge' => [
                        'type' => 'badge',
                        'badge_key' => self::requiredText($data['badge_key'] ?? null, 64, 'badge key'),
                    ],
                    'cash' => [
                        'type' => 'cash',
                        'minor_units' => self::int($data['cash_minor_units'] ?? null, 'cash amount'),
                        'currency' => self::requiredText($data['cash_currency'] ?? null, 3, 'cash currency'),
                    ],
                    'benefit' => [
                        'type' => 'benefit',
                        'benefit_key' => self::requiredText($data['benefit_key'] ?? null, 64, 'benefit key'),
                        'conditions' => self::lines($data['benefit_conditions'] ?? null),
                    ],
                };
            }
        }

        if ($components === []) {
            throw new InvalidArgumentException('At least one reward component must be enabled.');
        }

        $rules = [
            'schema' => RewardRuleValidator::SCHEMA,
            'mode' => $mode->value,
            'components' => $components,
            'eligibility' => [
                'outcomes' => self::stringList($data['eligibility_outcomes'] ?? null, 'eligibility outcomes'),
                'categories' => self::lines($data['eligibility_categories'] ?? null),
                'min_verification' => self::requiredText($data['min_verification'] ?? null, 32, 'minimum verification'),
                'first_only' => (bool) ($data['first_only'] ?? false),
                'exclude_self_credit' => (bool) ($data['exclude_self_credit'] ?? true),
                'require_enrollment' => (bool) ($data['require_enrollment'] ?? true),
                'require_claimed_identity' => (bool) ($data['require_claimed_identity'] ?? true),
            ],
            'windows' => [
                'preclaim_lookback_days' => self::int($data['preclaim_lookback_days'] ?? 30, 'preclaim lookback'),
                'hold_days' => self::int($data['hold_days'] ?? 0, 'hold days'),
            ],
            'caps' => self::caps($data),
            'holds' => [
                'ambiguous_device' => (bool) ($data['ambiguous_device'] ?? true),
                'manual_review_outcomes' => self::stringList($data['manual_review_outcomes'] ?? [], 'manual review outcomes', true),
            ],
        ];

        if (array_key_exists('milestone_rows', $data)) {
            $rules['milestones'] = self::milestoneRows($data['milestone_rows'], $mode);
        }

        $terms = [
            'label' => self::requiredText($data['terms_label'] ?? null, 191, 'terms label'),
            'version_label' => self::requiredText($data['terms_version_label'] ?? null, 32, 'terms version label'),
            'body' => self::requiredText($data['terms_body'] ?? null, 20000, 'terms body'),
            'material' => [
                'mode' => $mode->value,
                'earn_basis' => self::requiredText($data['terms_earn_basis'] ?? null, 2000, 'earn basis'),
                'changes' => self::requiredText($data['terms_changes'] ?? null, 2000, 'changes clause'),
                'no_ranking' => self::requiredText($data['terms_no_ranking'] ?? null, 2000, 'no-ranking clause'),
            ],
        ];

        return ['rules' => $rules, 'terms' => $terms];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function componentRows(RewardProgramMode $mode, mixed $rows): array
    {
        if (! is_array($rows) || ! array_is_list($rows) || $rows === [] || count($rows) > 5) {
            throw new InvalidArgumentException('Publish component rows must be a non-empty list of up to 5 entries.');
        }

        $allowed = array_map(static fn ($component): string => $component->value, $mode->allowedComponents());
        $components = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new InvalidArgumentException("Publish component row [{$index}] must be an associative map.");
            }

            self::assertAllowedKeys($row, ['type', 'variants'], "Publish component row [{$index}]");

            $type = self::requiredText($row['type'] ?? null, 16, 'component type');

            if (! in_array($type, $allowed, true)) {
                throw new InvalidArgumentException("Publish component type [{$type}] is not allowed in mode [{$mode->value}].");
            }

            $variants = $row['variants'] ?? null;

            if (! is_array($variants) || ! array_is_list($variants) || $variants === [] || count($variants) > 20) {
                throw new InvalidArgumentException("Publish component [{$type}] needs one or more outcome variants.");
            }

            $components[] = [
                'type' => $type,
                'variants' => array_map(static fn (mixed $variant): array => self::componentVariant($type, $variant), $variants),
            ];
        }

        return $components;
    }

    /**
     * @return array<string, mixed>
     */
    private static function componentVariant(string $type, mixed $variant): array
    {
        if (! is_array($variant) || array_is_list($variant)) {
            throw new InvalidArgumentException("Publish component [{$type}] variants must be associative maps.");
        }

        $rewardKeys = match ($type) {
            'recognition' => ['template'],
            'points' => ['points', 'unit'],
            'badge' => ['badge_key'],
            'cash' => ['minor_units', 'currency'],
            'benefit' => ['benefit_key', 'conditions'],
            default => [],
        };

        self::assertAllowedKeys(
            $variant,
            ['outcomes', 'roles', 'min_verification', 'first_only', ...$rewardKeys],
            "Publish component [{$type}] variant",
        );

        $common = [
            'outcomes' => self::stringList($variant['outcomes'] ?? null, 'component outcomes'),
            'roles' => self::stringList($variant['roles'] ?? null, 'component beneficiary roles'),
            'min_verification' => self::requiredText($variant['min_verification'] ?? null, 32, 'component minimum verification'),
            'first_only' => (bool) ($variant['first_only'] ?? false),
        ];

        return $common + match ($type) {
            'recognition' => array_filter([
                'template' => self::text($variant['template'] ?? null, 64),
            ], static fn (mixed $value): bool => $value !== null),
            'points' => [
                'points' => self::int($variant['points'] ?? null, 'points amount'),
                'unit' => self::requiredText($variant['unit'] ?? null, 16, 'points unit'),
            ],
            'badge' => [
                'badge_key' => self::requiredText($variant['badge_key'] ?? null, 64, 'badge key'),
            ],
            'cash' => [
                'minor_units' => self::int($variant['minor_units'] ?? null, 'cash amount'),
                'currency' => self::requiredText($variant['currency'] ?? null, 3, 'cash currency'),
            ],
            'benefit' => [
                'benefit_key' => self::requiredText($variant['benefit_key'] ?? null, 64, 'benefit key'),
                'conditions' => self::lines($variant['conditions'] ?? null),
            ],
            default => throw new InvalidArgumentException("Publish component type [{$type}] is not supported."),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function milestoneRows(mixed $rows, RewardProgramMode $mode): array
    {
        if (! is_array($rows) || ($rows !== [] && ! array_is_list($rows)) || count($rows) > 20) {
            throw new InvalidArgumentException('Publish milestones must be a list of up to 20 entries.');
        }

        $milestones = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new InvalidArgumentException("Publish milestone row [{$index}] must be an associative map.");
            }

            self::assertAllowedKeys(
                $row,
                ['key', 'label', 'outcomes', 'roles', 'min_verification', 'first_only', 'target_count', 'window_days', 'reward_type', 'template', 'points', 'unit', 'badge_key'],
                "Publish milestone row [{$index}]",
            );

            $type = self::requiredText($row['reward_type'] ?? null, 16, 'milestone grant type');

            $componentType = RewardComponent::tryFrom($type);

            if ($componentType instanceof RewardComponent && ! in_array($componentType, $mode->allowedComponents(), true)) {
                throw new InvalidArgumentException("Publish milestone grant type [{$type}] is not allowed in mode [{$mode->value}].");
            }

            $grant = match ($type) {
                'recognition' => array_filter([
                    'type' => $type,
                    'template' => self::text($row['template'] ?? null, 64),
                ], static fn (mixed $value): bool => $value !== null),
                'points' => [
                    'type' => $type,
                    'points' => self::int($row['points'] ?? null, 'milestone points grant'),
                    'unit' => self::requiredText($row['unit'] ?? null, 16, 'milestone points unit'),
                ],
                'badge' => [
                    'type' => $type,
                    'badge_key' => self::requiredText($row['badge_key'] ?? null, 64, 'milestone badge key'),
                ],
                default => throw new InvalidArgumentException('Milestone grants support recognition, points, or badges.'),
            };

            $milestones[] = [
                'key' => self::requiredText($row['key'] ?? null, 64, 'milestone key'),
                'label' => self::requiredText($row['label'] ?? null, 191, 'milestone label'),
                'outcomes' => self::stringList($row['outcomes'] ?? null, 'milestone outcomes'),
                'roles' => self::stringList($row['roles'] ?? null, 'milestone beneficiary roles'),
                'min_verification' => self::requiredText($row['min_verification'] ?? null, 32, 'milestone minimum verification'),
                'first_only' => (bool) ($row['first_only'] ?? false),
                'target_count' => self::int($row['target_count'] ?? null, 'milestone target count'),
                'window_days' => self::int($row['window_days'] ?? null, 'milestone cycle window'),
                'reward' => $grant,
            ];
        }

        return $milestones;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function componentEnabled(RewardProgramMode $mode, string $type, array $data): bool
    {
        if ($mode === RewardProgramMode::Combined) {
            return (bool) ($data['component_' . $type] ?? in_array($type, ['recognition', 'points', 'badge'], true));
        }

        foreach ($mode->allowedComponents() as $allowed) {
            if ($allowed->value === $type) {
                return true;
            }
        }

        return false;
    }

    private static function requiredText(mixed $value, int $max, string $label): string
    {
        if (! is_string($value) || mb_trim($value) === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException("Publish {$label} must be 1..{$max} characters.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $allowed
     */
    private static function assertAllowedKeys(array $values, array $allowed, string $context): void
    {
        foreach (array_keys($values) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("{$context} has an unknown key [" . (string) $key . '].');
            }
        }
    }

    private static function text(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Publish template must be up to ' . $max . ' characters.');
        }

        return $value;
    }

    private static function int(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }

        // Livewire dehydrates numeric inputs as floats; whole floats are
        // the same integer the operator typed.
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', mb_trim($value)) === 1) {
            return (int) mb_trim($value);
        }

        throw new InvalidArgumentException("Publish {$label} must be an integer, got [" . get_debug_type($value) . '].');
    }

    /**
     * @return list<string>
     */
    private static function lines(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(
                array_map(static fn (mixed $line): string => is_string($line) ? mb_trim($line) : '', $value),
                static fn (string $line): bool => $line !== '',
            ));
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Publish list fields take one entry per line.');
        }

        return array_values(array_filter(
            array_map(static fn (string $line): string => mb_trim($line), preg_split('/\r\n|\r|\n/', $value) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $label, bool $allowEmpty = false): array
    {
        $lines = is_array($value) && array_is_list($value) ? self::lines($value) : self::lines($value);

        if ($lines === [] && ! $allowEmpty) {
            throw new InvalidArgumentException("Publish {$label} needs at least one entry.");
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{scope: string, limit: int}>
     */
    private static function caps(array $data): array
    {
        $caps = [];

        for ($row = 1; $row <= 3; $row++) {
            $scope = $data['cap_scope_' . $row] ?? null;

            if ($scope === null || $scope === '') {
                continue;
            }

            if (! is_string($scope)) {
                throw new InvalidArgumentException('Publish cap scope must be a string.');
            }

            $caps[] = ['scope' => $scope, 'limit' => self::int($data['cap_limit_' . $row] ?? null, 'cap limit')];
        }

        return $caps;
    }
}
