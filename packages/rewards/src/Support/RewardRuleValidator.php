<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardVerificationLevel;
use InvalidArgumentException;

/**
 * Component-scoped structured validation for reward rules (schema rewards-core/v1).
 *
 * Strict allowlists everywhere: unknown top-level/section/component keys are
 * rejected, outcomes must be allowlisted, amounts are strict integers with
 * explicit bounds. Rules are pure data — there is no expression field and no
 * evaluation, so expression injection is impossible by construction.
 */
final class RewardRuleValidator
{
    public const string SCHEMA = 'rewards-core/v1';

    public const int MAX_POINTS = 1000000;

    public const int MAX_MINOR_UNITS = 999999999999;

    /**
     * @var list<string>
     */
    public const array CURRENCIES = ['MYR', 'USD', 'SGD', 'IDR', 'THB', 'GBP', 'EUR', 'AUD'];

    /**
     * @var list<string>
     */
    public const array CAP_SCOPES = [
        'recipient_source_component',
        'recipient_program_day',
        'recipient_program_month',
        'program_day',
        'program_total',
        'family_source_component',
    ];

    /**
     * @return list<string>
     */
    public static function currencies(): array
    {
        $configured = config('rewards.currencies', self::CURRENCIES);

        return is_array($configured) ? array_values($configured) : self::CURRENCIES;
    }

    public static function maxPoints(): int
    {
        return (int) config('rewards.limits.max_points', self::MAX_POINTS);
    }

    public static function maxMinorUnits(): int
    {
        return (int) config('rewards.limits.max_minor_units', self::MAX_MINOR_UNITS);
    }

    /**
     * @return list<string>
     */
    public static function capScopes(): array
    {
        $configured = config('rewards.cap_scopes', self::CAP_SCOPES);

        return is_array($configured) ? array_values($configured) : self::CAP_SCOPES;
    }

    /**
     * @return list<string>
     */
    public static function outcomes(): array
    {
        $configured = config('rewards.outcomes', []);

        return is_array($configured) ? array_values($configured) : [];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return list<string>
     */
    public function errors(array $rules, RewardProgramMode $mode, string $ownFamilyKey): array
    {
        $errors = [];

        foreach (array_keys($rules) as $key) {
            if (! in_array($key, ['schema', 'mode', 'components', 'eligibility', 'windows', 'caps', 'stacking', 'holds', 'milestones'], true)) {
                $errors[] = "Unknown rules key [{$key}].";
            }
        }

        if (($rules['schema'] ?? null) !== self::SCHEMA) {
            $errors[] = 'Rules schema must be [' . self::SCHEMA . '].';
        }

        if (($rules['mode'] ?? null) !== $mode->value) {
            $errors[] = 'Rules mode must match the program mode [' . $mode->value . '].';
        }

        $errors = [...$errors, ...$this->componentErrors($rules['components'] ?? null, $mode)];
        $errors = [...$errors, ...$this->eligibilityErrors($rules['eligibility'] ?? null, $rules['components'] ?? null)];
        $eligibilityOutcomes = is_array($rules['eligibility'] ?? null)
            ? ($rules['eligibility']['outcomes'] ?? null)
            : null;
        $errors = [...$errors, ...$this->scopedOutcomeErrors($rules['components'] ?? null, $eligibilityOutcomes, $rules['milestones'] ?? null)];
        $errors = [...$errors, ...$this->windowsErrors($rules['windows'] ?? null)];
        $errors = [...$errors, ...$this->capsErrors($rules['caps'] ?? null)];
        $errors = [...$errors, ...$this->stackingErrors($rules['stacking'] ?? null, $ownFamilyKey)];
        $errors = [...$errors, ...$this->holdsErrors($rules['holds'] ?? null)];
        $errors = [...$errors, ...$this->milestoneErrors($rules['milestones'] ?? null, $mode)];

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function scopedOutcomeErrors(mixed $components, mixed $eligibilityOutcomes, mixed $milestones): array
    {
        if (! is_array($eligibilityOutcomes) || ! array_is_list($eligibilityOutcomes)) {
            return [];
        }

        $errors = [];

        if (is_array($components)) {
            foreach ($components as $componentIndex => $component) {
                if (! is_array($component) || ! is_array($component['variants'] ?? null)) {
                    continue;
                }

                foreach ($component['variants'] as $variantIndex => $variant) {
                    $outcomes = is_array($variant) && is_array($variant['outcomes'] ?? null) ? $variant['outcomes'] : [];

                    foreach ($outcomes as $outcome) {
                        if (is_string($outcome) && ! in_array($outcome, $eligibilityOutcomes, true)) {
                            $errors[] = "Component [{$componentIndex}] variant [{$variantIndex}] outcome [{$outcome}] is outside program eligibility.";
                        }
                    }
                }
            }
        }

        if (is_array($milestones)) {
            foreach ($milestones as $index => $milestone) {
                $outcomes = is_array($milestone) && is_array($milestone['outcomes'] ?? null) ? $milestone['outcomes'] : [];

                foreach ($outcomes as $outcome) {
                    if (is_string($outcome) && ! in_array($outcome, $eligibilityOutcomes, true)) {
                        $errors[] = "Milestone [{$index}] outcome [{$outcome}] is outside program eligibility.";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function assertValid(array $rules, RewardProgramMode $mode, string $ownFamilyKey): void
    {
        $errors = $this->errors($rules, $mode, $ownFamilyKey);

        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid reward rules: ' . implode(' ', $errors));
        }
    }

    /**
     * @param  array<string, mixed>  $terms
     * @param  array<string, mixed>|null  $rules  Authoritative policy for consistency checks.
     * @return list<string>
     */
    public function termsErrors(array $terms, ?array $rules = null): array
    {
        $errors = [];

        foreach (array_keys($terms) as $key) {
            if (! in_array($key, ['label', 'version_label', 'body', 'material'], true)) {
                $errors[] = "Unknown terms key [{$key}].";
            }
        }

        $label = $terms['label'] ?? null;
        if (! is_string($label) || $label === '' || mb_strlen($label) > 191) {
            $errors[] = 'Terms label must be a non-empty string up to 191 characters.';
        }

        $versionLabel = $terms['version_label'] ?? null;
        if (! is_string($versionLabel) || $versionLabel === '' || mb_strlen($versionLabel) > 32) {
            $errors[] = 'Terms version_label must be a non-empty string up to 32 characters.';
        }

        $body = $terms['body'] ?? null;
        if (! is_string($body) || $body === '' || mb_strlen($body) > 20000) {
            $errors[] = 'Terms body must be a non-empty string up to 20000 characters.';
        }

        $material = $terms['material'] ?? null;
        if (! is_array($material) || $material === [] || array_is_list($material)) {
            $errors[] = 'Terms material must be a non-empty associative map of material clauses.';
        } else {
            foreach ($material as $key => $value) {
                if (! is_string($key) || $key === '' || mb_strlen($key) > 64) {
                    $errors[] = 'Material clause keys must be non-empty strings up to 64 characters.';

                    break;
                }
                if (! is_scalar($value) && ! is_array($value)) {
                    $errors[] = "Material clause [{$key}] must be scalar or array data.";

                    break;
                }
                if (is_string($value) && mb_strlen($value) > 2000) {
                    $errors[] = "Material clause [{$key}] exceeds 2000 characters.";

                    break;
                }
            }

            foreach (['mode', 'earn_basis', 'changes'] as $required) {
                $clause = $material[$required] ?? null;

                if (! is_string($clause) || $clause === '' || mb_strlen($clause) > 2000) {
                    $errors[] = "Material clause [{$required}] is mandatory and must be a non-empty string up to 2000 characters.";
                }
            }

            if ($rules !== null && is_string($material['mode'] ?? null) && ($rules['mode'] ?? null) !== $material['mode']) {
                $errors[] = 'Material clause [mode] must match the authoritative rules mode [' . ($rules['mode'] ?? 'none') . '].';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $terms
     * @param  array<string, mixed>|null  $rules
     */
    public function assertValidTerms(array $terms, ?array $rules = null): void
    {
        $errors = $this->termsErrors($terms, $rules);

        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid reward terms: ' . implode(' ', $errors));
        }
    }

    /**
     * @return list<string>
     */
    private function componentErrors(mixed $components, RewardProgramMode $mode): array
    {
        if (! is_array($components) || $components === [] || ! array_is_list($components)) {
            return ['Components must be a non-empty list.'];
        }

        if (count($components) > 5) {
            return ['At most 5 components are allowed.'];
        }

        $allowed = array_map(static fn (RewardComponent $c): string => $c->value, $mode->allowedComponents());
        $errors = [];
        $seen = [];

        foreach ($components as $index => $component) {
            if (! is_array($component) || array_is_list($component)) {
                $errors[] = "Component [{$index}] must be an associative map.";

                continue;
            }

            $type = $component['type'] ?? null;
            $enum = RewardComponent::tryFrom(is_string($type) ? $type : '');

            if (! $enum instanceof RewardComponent) {
                $errors[] = "Component [{$index}] has an unknown type.";

                continue;
            }

            if (in_array($enum->value, $seen, true)) {
                $errors[] = "Duplicate component [{$enum->value}] conflicts; each type may appear once.";
            }
            $seen[] = $enum->value;

            if (! in_array($enum->value, $allowed, true)) {
                $errors[] = "Component [{$enum->value}] is not allowed in mode [{$mode->value}].";
            }

            if (array_key_exists('variants', $component)) {
                foreach (array_keys($component) as $key) {
                    if (! in_array($key, ['type', 'variants'], true)) {
                        $errors[] = "Component [{$index}] ({$enum->value}) has an unknown key [{$key}].";
                    }
                }

                $errors = [...$errors, ...$this->componentVariantErrors($enum, $component['variants'], $index)];
            } else {
                $errors = [...$errors, ...$this->singleComponentErrors($enum, $component, $index)];
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function componentVariantErrors(RewardComponent $component, mixed $variants, int $index): array
    {
        if (! is_array($variants) || ! array_is_list($variants) || $variants === [] || count($variants) > 20) {
            return ["Component [{$index}] variants must be a non-empty list of up to 20 entries."];
        }

        $errors = [];
        $seenOutcomeRoles = [];

        foreach ($variants as $variantIndex => $variant) {
            $label = "component:{$index}:variant:{$variantIndex}";

            if (! is_array($variant) || array_is_list($variant)) {
                $errors[] = "Component variant [{$label}] must be an associative map.";

                continue;
            }

            foreach (array_keys($variant) as $key) {
                if (! in_array($key, ['outcomes', 'roles', 'min_verification', 'first_only', 'template', 'points', 'unit', 'badge_key', 'minor_units', 'currency', 'benefit_key', 'conditions'], true)) {
                    $errors[] = "Component variant [{$label}] has an unknown key [{$key}].";
                }
            }

            $outcomes = $variant['outcomes'] ?? null;
            $validOutcomes = [];
            if (! is_array($outcomes) || ! array_is_list($outcomes) || $outcomes === [] || count($outcomes) > 20) {
                $errors[] = "Component variant [{$label}] outcomes must be a non-empty allowlisted list.";
                $outcomes = [];
            } else {
                $seen = [];
                foreach ($outcomes as $outcome) {
                    if (! is_string($outcome) || ! in_array($outcome, self::outcomes(), true)) {
                        $errors[] = "Component variant [{$label}] outcomes must all be allowlisted values.";

                        continue;
                    }

                    if (in_array($outcome, $seen, true)) {
                        $errors[] = "Component variant [{$label}] has duplicate outcome [{$outcome}].";

                        continue;
                    }

                    $seen[] = $outcome;
                    $validOutcomes[] = $outcome;
                }
            }
            $outcomes = $validOutcomes;

            $roles = $variant['roles'] ?? null;
            $validRoles = [];
            if (! is_array($roles) || ! array_is_list($roles) || $roles === [] || count($roles) > count(RewardBeneficiaryRole::cases())) {
                $errors[] = "Component variant [{$label}] roles must be a non-empty allowlisted list.";
                $roles = [];
            } else {
                $seen = [];
                foreach ($roles as $role) {
                    if (! is_string($role) || ! in_array($role, RewardBeneficiaryRole::values(), true)) {
                        $errors[] = "Component variant [{$label}] roles must all be known beneficiary roles.";

                        continue;
                    }

                    if (in_array($role, $seen, true)) {
                        $errors[] = "Component variant [{$label}] has duplicate role [{$role}].";

                        continue;
                    }

                    $seen[] = $role;
                    $validRoles[] = $role;
                }
            }
            $roles = $validRoles;

            $minimum = RewardVerificationLevel::tryFrom((string) ($variant['min_verification'] ?? ''));
            if (! $minimum instanceof RewardVerificationLevel) {
                $errors[] = "Component variant [{$label}] min_verification must be a known verification level.";
            } elseif (in_array($component, [RewardComponent::Cash, RewardComponent::Benefit], true)
                && ! $minimum->satisfies(RewardVerificationLevel::StaffVerified)
            ) {
                $errors[] = "Component variant [{$label}] cash/benefit requires staff_verified or higher.";
            }

            if (! is_bool($variant['first_only'] ?? null)) {
                $errors[] = "Component variant [{$label}] first_only must be boolean.";
            }

            foreach ($outcomes as $outcome) {
                foreach ($roles as $role) {
                    $pair = $outcome . ':' . $role;

                    if (isset($seenOutcomeRoles[$pair])) {
                        $errors[] = "Component [{$index}] variants overlap for outcome [{$outcome}] and role [{$role}].";
                    }

                    $seenOutcomeRoles[$pair] = true;
                }
            }

            $amount = $variant;
            unset($amount['outcomes'], $amount['roles'], $amount['min_verification'], $amount['first_only']);
            $errors = [...$errors, ...$this->singleComponentErrors($component, $amount, $label)];
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $component
     * @return list<string>
     */
    private function singleComponentErrors(RewardComponent $enum, array $component, int | string $index): array
    {
        $amountish = ['amount', 'points', 'minor_units', 'currency', 'value'];
        $errors = [];

        $forbidAmount = function (array $allowedKeys) use ($component, $enum, $index, $amountish): array {
            $found = [];

            foreach (array_keys($component) as $key) {
                if (! in_array($key, $allowedKeys, true) && ! in_array($key, $amountish, true)) {
                    $found[] = "Component [{$index}] ({$enum->value}) has an unknown key [{$key}].";
                }
            }

            foreach ($amountish as $key) {
                if (array_key_exists($key, $component) && ! in_array($key, $allowedKeys, true)) {
                    $found[] = "Component [{$index}] ({$enum->value}) must not carry [{$key}].";
                }
            }

            return $found;
        };

        switch ($enum) {
            case RewardComponent::Recognition:
                $errors = [...$errors, ...$forbidAmount(['type', 'template'])];
                $template = $component['template'] ?? null;
                if ($template !== null && (! is_string($template) || $template === '' || mb_strlen($template) > 64)) {
                    $errors[] = "Component [{$index}] (recognition) template must be up to 64 characters.";
                }

                break;

            case RewardComponent::Points:
                $errors = [...$errors, ...$forbidAmount(['type', 'points', 'unit'])];
                $points = $component['points'] ?? null;
                if (! is_int($points) || $points < 1 || $points > self::maxPoints()) {
                    $errors[] = "Component [{$index}] (points) points must be an integer 1.." . self::maxPoints() . '.';
                }
                $unit = $component['unit'] ?? null;
                if (! is_string($unit) || preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-]{0,15}$/', $unit) !== 1) {
                    $errors[] = "Component [{$index}] (points) unit must be 1..16 alphanumerics.";
                }

                break;

            case RewardComponent::Badge:
                $errors = [...$errors, ...$forbidAmount(['type', 'badge_key'])];
                $badgeKey = $component['badge_key'] ?? null;
                if (! is_string($badgeKey) || preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $badgeKey) !== 1) {
                    $errors[] = "Component [{$index}] (badge) badge_key must be a 1..64 slug.";
                }

                break;

            case RewardComponent::Cash:
                $errors = [...$errors, ...$forbidAmount(['type', 'minor_units', 'currency'])];
                $minor = $component['minor_units'] ?? null;
                if (! is_int($minor) || $minor < 1 || $minor > self::maxMinorUnits()) {
                    $errors[] = "Component [{$index}] (cash) minor_units must be an integer 1.." . self::maxMinorUnits() . '.';
                }
                $currency = $component['currency'] ?? null;
                if (! is_string($currency) || ! in_array($currency, self::currencies(), true)) {
                    $errors[] = "Component [{$index}] (cash) currency must be one of [" . implode(',', self::currencies()) . '].';
                }

                break;

            case RewardComponent::Benefit:
                $errors = [...$errors, ...$forbidAmount(['type', 'benefit_key', 'conditions'])];
                $benefitKey = $component['benefit_key'] ?? null;
                if (! is_string($benefitKey) || preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $benefitKey) !== 1) {
                    $errors[] = "Component [{$index}] (benefit) benefit_key must be a 1..64 slug.";
                }
                $conditions = $component['conditions'] ?? null;
                if (! is_array($conditions) || ! array_is_list($conditions) || $conditions === [] || count($conditions) > 20) {
                    $errors[] = "Component [{$index}] (benefit) conditions must be an explicit list of 1..20 entries.";
                } else {
                    foreach ($conditions as $condition) {
                        if (! is_string($condition) || $condition === '' || mb_strlen($condition) > 191) {
                            $errors[] = "Component [{$index}] (benefit) conditions must be non-empty strings up to 191 characters.";

                            break;
                        }
                    }
                }

                break;
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function eligibilityErrors(mixed $eligibility, mixed $components): array
    {
        if (! is_array($eligibility) || array_is_list($eligibility)) {
            return ['Eligibility must be an associative map.'];
        }

        $errors = [];

        foreach (array_keys($eligibility) as $key) {
            if (! in_array($key, ['outcomes', 'categories', 'min_verification', 'first_only', 'exclude_self_credit', 'require_enrollment', 'require_claimed_identity'], true)) {
                $errors[] = "Unknown eligibility key [{$key}].";
            }
        }

        $outcomes = $eligibility['outcomes'] ?? null;
        if (! is_array($outcomes) || ! array_is_list($outcomes) || $outcomes === [] || count($outcomes) > 20) {
            $errors[] = 'Eligibility outcomes must be a list of 1..20 allowlisted outcomes.';
        } else {
            $seen = [];
            $allowed = self::outcomes();
            foreach ($outcomes as $outcome) {
                if (! is_string($outcome) || ! in_array($outcome, $allowed, true)) {
                    $errors[] = 'Eligibility outcomes must all be allowlisted outcome values.';

                    break;
                }
                if (in_array($outcome, $seen, true)) {
                    $errors[] = "Duplicate eligibility outcome [{$outcome}].";

                    break;
                }
                $seen[] = $outcome;
            }
        }

        $categories = $eligibility['categories'] ?? [];
        if (! is_array($categories) || ($categories !== [] && ! array_is_list($categories)) || count($categories) > 50) {
            $errors[] = 'Eligibility categories must be a list of up to 50 slugs (empty means all).';
        } else {
            $seen = [];
            foreach ($categories as $category) {
                if (! is_string($category) || preg_match('/^[a-z0-9][a-z0-9_\-]{0,31}$/', $category) !== 1) {
                    $errors[] = 'Eligibility categories must be 1..32 slugs.';

                    break;
                }
                if (in_array($category, $seen, true)) {
                    $errors[] = "Duplicate eligibility category [{$category}].";

                    break;
                }
                $seen[] = $category;
            }
        }

        $minVerification = RewardVerificationLevel::tryFrom((string) ($eligibility['min_verification'] ?? ''));
        if (! $minVerification instanceof RewardVerificationLevel) {
            $errors[] = 'Eligibility min_verification must be a known verification level.';
        } elseif ($this->componentsInvolveMoneyOrBenefit($components) && ! $minVerification->satisfies(RewardVerificationLevel::StaffVerified)) {
            $errors[] = 'Cash/benefit components require min_verification staff_verified or higher; accepted registration is not attendance.';
        }

        if (array_key_exists('first_only', $eligibility) && ! is_bool($eligibility['first_only'])) {
            $errors[] = 'Eligibility [first_only] must be boolean.';
        }

        foreach ([
            'exclude_self_credit' => 'self credit is never rewarded',
            'require_enrollment' => 'enrollment is required for rewards',
            'require_claimed_identity' => 'no reward is issued to an unclaimed guest identity',
        ] as $flag => $reason) {
            if (($eligibility[$flag] ?? null) !== true) {
                $errors[] = "Eligibility [{$flag}] must be true; {$reason}. A different purpose needs an explicit policy contract, not a disabled protection flag.";
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function windowsErrors(mixed $windows): array
    {
        if ($windows === null) {
            return [];
        }

        if (! is_array($windows) || array_is_list($windows)) {
            return ['Windows must be an associative map.'];
        }

        $errors = [];

        foreach (array_keys($windows) as $key) {
            if (! in_array($key, ['preclaim_lookback_days', 'hold_days'], true)) {
                $errors[] = "Unknown windows key [{$key}].";
            }
        }

        $lookback = $windows['preclaim_lookback_days'] ?? null;
        if ($lookback !== null && (! is_int($lookback) || $lookback < 0 || $lookback > 90)) {
            $errors[] = 'Windows preclaim_lookback_days must be null or an integer 0..90.';
        }

        $hold = $windows['hold_days'] ?? 0;
        if (! is_int($hold) || $hold < 0 || $hold > 60) {
            $errors[] = 'Windows hold_days must be an integer 0..60.';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function capsErrors(mixed $caps): array
    {
        if ($caps === null) {
            return [];
        }

        if (! is_array($caps) || ($caps !== [] && ! array_is_list($caps)) || count($caps) > 20) {
            return ['Caps must be a list of up to 20 entries.'];
        }

        $errors = [];
        $seen = [];

        foreach ($caps as $index => $cap) {
            if (! is_array($cap) || array_is_list($cap)) {
                $errors[] = "Cap [{$index}] must be an associative map.";

                continue;
            }

            foreach (array_keys($cap) as $key) {
                if (! in_array($key, ['scope', 'limit'], true)) {
                    $errors[] = "Cap [{$index}] has an unknown key [{$key}].";
                }
            }

            $scope = $cap['scope'] ?? null;
            if (! is_string($scope) || ! in_array($scope, self::capScopes(), true)) {
                $errors[] = "Cap [{$index}] scope must be one of [" . implode(',', self::capScopes()) . '].';

                continue;
            }

            if (in_array($scope, $seen, true)) {
                $errors[] = "Duplicate cap scope [{$scope}].";
            }
            $seen[] = $scope;

            $limit = $cap['limit'] ?? null;
            if (! is_int($limit) || $limit < 1 || $limit > 1000000) {
                $errors[] = "Cap [{$index}] limit must be an integer 1..1000000.";
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function stackingErrors(mixed $stacking, string $ownFamilyKey): array
    {
        if ($stacking === null) {
            return [];
        }

        if (! is_array($stacking) || array_is_list($stacking)) {
            return ['Stacking must be an associative map.'];
        }

        $errors = [];

        foreach (array_keys($stacking) as $key) {
            if ($key !== 'compatible_with') {
                $errors[] = "Unknown stacking key [{$key}].";
            }
        }

        $compatible = $stacking['compatible_with'] ?? [];
        if (! is_array($compatible) || ($compatible !== [] && ! array_is_list($compatible)) || count($compatible) > 20) {
            $errors[] = 'Stacking compatible_with must be a list of up to 20 family keys.';
        } else {
            $seen = [];
            foreach ($compatible as $family) {
                if (! is_string($family) || preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $family) !== 1) {
                    $errors[] = 'Stacking compatible_with entries must be 1..64 family slugs.';

                    break;
                }
                if ($family === $ownFamilyKey) {
                    $errors[] = 'Stacking compatible_with must not reference the program itself (circular).';

                    break;
                }
                if (in_array($family, $seen, true)) {
                    $errors[] = "Duplicate stacking entry [{$family}].";

                    break;
                }
                $seen[] = $family;
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function holdsErrors(mixed $holds): array
    {
        if ($holds === null) {
            return [];
        }

        if (! is_array($holds) || array_is_list($holds)) {
            return ['Holds must be an associative map.'];
        }

        $errors = [];

        foreach (array_keys($holds) as $key) {
            if (! in_array($key, ['ambiguous_device', 'manual_review_outcomes'], true)) {
                $errors[] = "Unknown holds key [{$key}].";
            }
        }

        if (array_key_exists('ambiguous_device', $holds) && ! is_bool($holds['ambiguous_device'])) {
            $errors[] = 'Holds ambiguous_device must be boolean.';
        }

        $manual = $holds['manual_review_outcomes'] ?? [];
        if (! is_array($manual) || ($manual !== [] && ! array_is_list($manual)) || count($manual) > 20) {
            $errors[] = 'Holds manual_review_outcomes must be a list of up to 20 allowlisted outcomes.';
        } else {
            $allowed = self::outcomes();

            foreach ($manual as $outcome) {
                if (! is_string($outcome) || ! in_array($outcome, $allowed, true)) {
                    $errors[] = 'Holds manual_review_outcomes must all be allowlisted outcome values.';

                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function milestoneErrors(mixed $milestones, RewardProgramMode $mode): array
    {
        if ($milestones === null) {
            return [];
        }

        if (! is_array($milestones) || ($milestones !== [] && ! array_is_list($milestones)) || count($milestones) > 20) {
            return ['Milestones must be a list of up to 20 definitions.'];
        }

        $errors = [];
        $seen = [];

        foreach ($milestones as $index => $definition) {
            if (! is_array($definition) || array_is_list($definition)) {
                $errors[] = "Milestone [{$index}] must be an associative map.";

                continue;
            }

            foreach (array_keys($definition) as $key) {
                if (! in_array($key, ['key', 'label', 'outcomes', 'roles', 'min_verification', 'first_only', 'target_count', 'window_days', 'reward'], true)) {
                    $errors[] = "Milestone [{$index}] has an unknown key [{$key}].";
                }
            }

            $key = $definition['key'] ?? null;

            if (! is_string($key) || preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $key) !== 1) {
                $errors[] = "Milestone [{$index}] key must be a 1..64 slug.";
            } elseif (in_array($key, $seen, true)) {
                $errors[] = "Duplicate milestone key [{$key}].";
            } else {
                $seen[] = $key;
            }

            $label = $definition['label'] ?? null;
            if ($label !== null && (! is_string($label) || mb_trim($label) === '' || mb_strlen($label) > 191)) {
                $errors[] = "Milestone [{$index}] label must be 1..191 characters when supplied.";
            }

            if (array_key_exists('outcomes', $definition)) {
                $outcomes = $definition['outcomes'];
                if (! is_array($outcomes) || ! array_is_list($outcomes) || $outcomes === [] || count($outcomes) > 20) {
                    $errors[] = "Milestone [{$index}] outcomes must be a non-empty allowlisted list.";
                } else {
                    $seenOutcomes = [];

                    foreach ($outcomes as $outcome) {
                        if (! is_string($outcome) || ! in_array($outcome, self::outcomes(), true)) {
                            $errors[] = "Milestone [{$index}] outcomes must all be allowlisted values.";

                            break;
                        }

                        if (in_array($outcome, $seenOutcomes, true)) {
                            $errors[] = "Milestone [{$index}] has duplicate outcome [{$outcome}].";

                            break;
                        }

                        $seenOutcomes[] = $outcome;
                    }
                }
            }

            if (array_key_exists('roles', $definition)) {
                $roles = $definition['roles'];
                if (! is_array($roles) || ! array_is_list($roles) || $roles === [] || count($roles) > count(RewardBeneficiaryRole::cases())) {
                    $errors[] = "Milestone [{$index}] roles must be a non-empty allowlisted list.";
                } else {
                    $seenRoles = [];

                    foreach ($roles as $role) {
                        if (! is_string($role) || ! in_array($role, RewardBeneficiaryRole::values(), true)) {
                            $errors[] = "Milestone [{$index}] roles must all be known beneficiary roles.";

                            break;
                        }

                        if (in_array($role, $seenRoles, true)) {
                            $errors[] = "Milestone [{$index}] has duplicate role [{$role}].";

                            break;
                        }

                        $seenRoles[] = $role;
                    }
                }
            }

            if (array_key_exists('min_verification', $definition)
                && ! RewardVerificationLevel::tryFrom((string) $definition['min_verification']) instanceof RewardVerificationLevel
            ) {
                $errors[] = "Milestone [{$index}] min_verification must be a known verification level.";
            }

            if (array_key_exists('first_only', $definition) && ! is_bool($definition['first_only'])) {
                $errors[] = "Milestone [{$index}] first_only must be boolean.";
            }

            $target = $definition['target_count'] ?? null;

            if (! is_int($target) || $target < 1 || $target > 1000000) {
                $errors[] = "Milestone [{$index}] target_count must be an integer 1..1000000.";
            }

            $window = $definition['window_days'] ?? null;

            if (! is_int($window) || $window < 1 || $window > 365) {
                $errors[] = "Milestone [{$index}] window_days must be an integer 1..365.";
            }

            $reward = $definition['reward'] ?? null;

            if (! is_array($reward) || array_is_list($reward)) {
                $errors[] = "Milestone [{$index}] reward must be an associative map.";

                continue;
            }

            $type = RewardComponent::tryFrom((string) ($reward['type'] ?? ''));

            if (! $type instanceof RewardComponent
                || ! in_array($type, [RewardComponent::Recognition, RewardComponent::Points, RewardComponent::Badge], true)
            ) {
                $errors[] = "Milestone [{$index}] reward type must be recognition, points, or badge; cash/benefit settlement is WP6 scope.";

                continue;
            }

            if (! in_array($type, $mode->allowedComponents(), true)) {
                $errors[] = "Milestone [{$index}] reward type [{$type->value}] is not allowed in program mode [{$mode->value}].";
            }

            $errors = [...$errors, ...$this->singleComponentErrors($type, $reward, "milestone:{$index}")];
        }

        return $errors;
    }

    private function componentsInvolveMoneyOrBenefit(mixed $components): bool
    {
        if (! is_array($components)) {
            return false;
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $enum = RewardComponent::tryFrom((string) ($component['type'] ?? ''));

            if ($enum instanceof RewardComponent && $enum->requiresMoneyOrBenefitFulfillment()) {
                return true;
            }
        }

        return false;
    }
}
