<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Enums\RewardBeneficiaryRole;

/**
 * Canonical material-terms hashing. The version fingerprint covers the full
 * authoritative policy rules plus the declared material clauses; cosmetic
 * body/label/version_label edits do not change the hash, while any policy or
 * material clause change does — driving prospective reacceptance. Callers
 * cannot mask a rule change by leaving the material map unchanged.
 */
final class RewardMaterialTerms
{
    /**
     * @param  array<string, mixed>  $material
     */
    public static function hash(array $material): string
    {
        return hash('sha256', (string) json_encode(
            self::canonicalize($material),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $terms
     */
    public static function hashForVersion(array $rules, array $terms): string
    {
        $material = $terms['material'] ?? [];

        return self::hash([
            'rules' => self::normalizeRules($rules),
            'material' => is_array($material) ? $material : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private static function normalizeRules(array $rules): array
    {
        $eligibility = is_array($rules['eligibility'] ?? null) ? $rules['eligibility'] : [];
        $components = is_array($rules['components'] ?? null) ? $rules['components'] : [];

        foreach ($components as $index => $component) {
            if (! is_array($component) || ! is_string($component['type'] ?? null)) {
                continue;
            }

            $variants = $component['variants'] ?? null;

            if (! is_array($variants)) {
                $components[$index] = self::economicConfiguration($component['type'], $component);

                continue;
            }

            if (count($variants) === 1 && is_array($variants[0] ?? null)) {
                $variant = $variants[0];
                $variantOutcomes = $variant['outcomes'] ?? null;
                $eligibilityOutcomes = $eligibility['outcomes'] ?? null;
                $variantRoles = $variant['roles'] ?? null;
                $allRoles = RewardBeneficiaryRole::values();
                $variantRoleSet = is_array($variantRoles) ? array_values(array_unique($variantRoles)) : [];
                $allRoleSet = array_values(array_unique($allRoles));
                sort($variantRoleSet);
                sort($allRoleSet);

                if ($variantOutcomes === $eligibilityOutcomes
                    && is_array($variantRoles)
                    && $variantRoleSet === $allRoleSet
                    && ($variant['min_verification'] ?? null) === ($eligibility['min_verification'] ?? null)
                    && ($variant['first_only'] ?? null) === ($eligibility['first_only'] ?? null)) {
                    $components[$index] = self::economicConfiguration($component['type'], $variant);

                    continue;
                }
            }

            $components[$index] = [
                'type' => $component['type'],
                'variants' => array_map(
                    static fn (mixed $variant): mixed => is_array($variant)
                        ? [...array_intersect_key($variant, array_flip(['outcomes', 'roles', 'min_verification', 'first_only'])), ...self::economicConfiguration($component['type'], $variant)]
                        : $variant,
                    $variants,
                ),
            ];
        }

        $rules['components'] = $components;

        if (($rules['milestones'] ?? null) === []) {
            unset($rules['milestones']);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private static function economicConfiguration(string $type, array $configuration): array
    {
        $economicKeys = match ($type) {
            'recognition' => ['template'],
            'points' => ['points', 'unit'],
            'badge' => ['badge_key'],
            'cash' => ['minor_units', 'currency'],
            'benefit' => ['benefit_key', 'conditions'],
            default => [],
        };

        return ['type' => $type, ...array_intersect_key($configuration, array_flip($economicKeys))];
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            foreach ($value as $key => $entry) {
                $value[$key] = self::canonicalize($entry);
            }
        }

        return $value;
    }
}
