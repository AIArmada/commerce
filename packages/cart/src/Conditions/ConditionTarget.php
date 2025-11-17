<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionFilterOperator;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

final class ConditionTarget implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly ConditionScope $scope,
        public readonly ConditionPhase $phase,
        public readonly ConditionApplication $application,
        public readonly ?ConditionSelector $selector = null,
        public readonly array $meta = []
    ) {}

    public function __toString(): string
    {
        return $this->toDsl();
    }

    /**
     * @param  ConditionTarget|string|array<string, mixed>  $target  DSL string (`scope@phase/application`), structured array, or ConditionTarget instance
     */
    public static function from(mixed $target): self
    {
        if ($target instanceof self) {
            return $target;
        }

        if (is_string($target)) {
            $target = mb_trim($target);

            if ($target === '') {
                throw new InvalidArgumentException('Target string cannot be empty.');
            }

            return self::fromDsl($target);
        }

        if (is_array($target)) {
            return self::fromArray($target);
        }

        throw new InvalidArgumentException('Unable to build condition target from '.get_debug_type($target));
    }

    /**
     * @param  array{
     *     scope:ConditionScope|string,
     *     phase:ConditionPhase|string,
     *     application:ConditionApplication|string,
     *     selector?:ConditionSelector|array|null,
     *     meta?:array<string, mixed>
     * }  $data
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['scope'], $data['phase'], $data['application'])) {
            throw new InvalidArgumentException('Target array requires scope, phase, and application values.');
        }

        $scope = $data['scope'] instanceof ConditionScope
            ? $data['scope']
            : ConditionScope::fromString((string) $data['scope']);

        $phase = $data['phase'] instanceof ConditionPhase
            ? $data['phase']
            : ConditionPhase::fromString((string) $data['phase']);

        $application = $data['application'] instanceof ConditionApplication
            ? $data['application']
            : ConditionApplication::fromString((string) $data['application']);

        $selector = null;
        if (isset($data['selector'])) {
            if ($data['selector'] instanceof ConditionSelector) {
                $selector = $data['selector'];
            } elseif (is_array($data['selector'])) {
                $selector = ConditionSelector::fromArray($data['selector']);
            }
        }

        return new self(
            $scope,
            $phase,
            $application,
            $selector,
            $data['meta'] ?? []
        );
    }

    public static function fromDsl(string $dsl): self
    {
        $dsl = mb_trim($dsl);

        if ($dsl === '') {
            throw new InvalidArgumentException('Target DSL cannot be empty.');
        }

        [$scopeSegment, $phaseSegment] = self::splitOnce($dsl, '@');
        [$phaseToken, $applicationSegment] = self::splitOnce($phaseSegment, '/');

        $scopeSelector = self::parseScopeSegment($scopeSegment);

        $groupingPreset = null;
        $applicationToken = $applicationSegment;
        if (str_contains($applicationSegment, '#')) {
            [$applicationToken, $groupingPreset] = self::splitOnce($applicationSegment, '#');
        }

        $application = ConditionApplication::fromString($applicationToken);
        $phase = ConditionPhase::fromString($phaseToken);

        $grouping = $groupingPreset !== null && $groupingPreset !== ''
            ? ConditionGrouping::forPreset($groupingPreset)
            : null;

        $selector = $scopeSelector['filters'] === null && $grouping === null
            ? null
            : new ConditionSelector(
                $scopeSelector['filters'] ?? [],
                $grouping
            );

        return new self(
            $scopeSelector['scope'],
            $phase,
            $application,
            $selector
        );
    }

    public function toArray(): array
    {
        return [
            'scope' => $this->scope->value,
            'phase' => $this->phase->value,
            'application' => $this->application->value,
            'selector' => $this->selector?->toArray(),
            'meta' => $this->meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toDsl(): string
    {
        $scopeSegment = $this->scope->value;
        $filters = $this->selector?->toDslFilters();

        if ($filters !== null && $filters !== '') {
            $scopeSegment .= ':'.$filters;
        }

        $phaseSegment = $this->phase->value;
        $applicationSegment = $this->application->value;

        $groupPreset = $this->selector?->grouping?->preset;
        if ($groupPreset) {
            $applicationSegment .= "#{$groupPreset}";
        }

        return "{$scopeSegment}@{$phaseSegment}/{$applicationSegment}";
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function with(array $meta): self
    {
        return new self(
            $this->scope,
            $this->phase,
            $this->application,
            $this->selector,
            array_merge($this->meta, $meta)
        );
    }

    /**
     * @return array{scope:ConditionScope, filters:?list<ConditionFilter>}
     */
    private static function parseScopeSegment(string $segment): array
    {
        if ($segment === '') {
            throw new InvalidArgumentException('Scope segment is required in target DSL.');
        }

        $scopeName = $segment;
        $filters = null;

        if (str_contains($segment, ':')) {
            [$scopeName, $filterSegment] = self::splitOnce($segment, ':');
            $filters = self::parseFilters($filterSegment);
        }

        $scope = ConditionScope::fromString($scopeName);

        return [
            'scope' => $scope,
            'filters' => $filters,
        ];
    }

    /**
     * @return list<ConditionFilter>
     */
    private static function parseFilters(string $segment): array
    {
        $tokens = array_filter(array_map('trim', explode(';', $segment)));
        $filters = [];

        foreach ($tokens as $token) {
            $filters[] = self::parseFilterToken($token);
        }

        return $filters;
    }

    private static function parseFilterToken(string $token): ConditionFilter
    {
        $pattern = '/^([A-Za-z0-9_.-]+)\s*(not-in|not_in|>=|<=|!=|=|>|<|in|!~|~|starts_with|ends_with)\s*(.+)$/i';

        if (preg_match($pattern, $token, $matches) !== 1) {
            throw new InvalidArgumentException("Unable to parse filter token [{$token}]");
        }

        $field = $matches[1];
        $operatorToken = $matches[2];
        $value = $matches[3];

        $operator = ConditionFilterOperator::fromString($operatorToken);

        return new ConditionFilter(mb_trim($field), $operator, self::parseValueToken($value, $operator));
    }

    private static function parseValueToken(string $rawValue, ConditionFilterOperator $operator): mixed
    {
        $rawValue = mb_trim($rawValue);

        if ($operator->requiresArrayValue() || (str_starts_with($rawValue, '[') && str_ends_with($rawValue, ']'))) {
            $inner = mb_trim($rawValue, '[]');

            if ($inner === '') {
                return [];
            }

            $parts = array_map('trim', explode(',', $inner));

            return array_map(fn ($token) => self::castScalarValue($token), $parts);
        }

        return self::castScalarValue($rawValue);
    }

    private static function castScalarValue(string $value): mixed
    {
        $value = mb_trim($value);

        if ($value === '') {
            return '';
        }

        if ((str_starts_with($value, "'") && str_ends_with($value, "'")) ||
            (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
            return stripcslashes(mb_substr($value, 1, -1));
        }

        $lower = mb_strtolower($value);

        return match ($lower) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => is_numeric($value)
                ? (str_contains($value, '.') ? (float) $value : (int) $value)
                : $value,
        };
    }

    /**
     * @return array<string>
     */
    private static function splitOnce(string $value, string $delimiter): array
    {
        $parts = explode($delimiter, $value, 2);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Malformed target segment [{$value}]");
        }

        return $parts;
    }
}
