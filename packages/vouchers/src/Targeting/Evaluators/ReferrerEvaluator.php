<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates referrer/traffic source targeting rules.
 *
 * Supports targeting based on:
 * - HTTP referrer URL
 * - UTM parameters
 * - Campaign source
 * - Traffic channel (organic, paid, social, email, direct)
 */
class ReferrerEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Referrer->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $request = $context->request;
        $operator = $rule['operator'] ?? 'contains';
        $values = $rule['values'] ?? [];

        if (empty($values)) {
            return true;
        }

        // Get referrer from request or context metadata
        $referrer = $request?->headers?->get('referer') ?? $request?->headers?->get('referrer') ?? '';
        $metadata = $context->metadata;
        $utmSource = $metadata['utm_source'] ?? $this->extractUtmSource($request);
        $utmMedium = $metadata['utm_medium'] ?? null;
        $utmCampaign = $metadata['utm_campaign'] ?? null;

        $ruleType = $rule['type'] ?? 'referrer';

        return match ($ruleType) {
            'referrer', 'referrer_url' => $this->matchReferrer($referrer, $values, $operator),
            'utm_source' => $this->matchValue($utmSource, $values, $operator),
            'utm_medium' => $this->matchValue($utmMedium, $values, $operator),
            'utm_campaign' => $this->matchValue($utmCampaign, $values, $operator),
            'channel' => $this->matchChannel($referrer, $utmSource, $utmMedium, $values),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Referrer->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['values']) || ! is_array($rule['values'])) {
            $errors[] = 'Values must be an array';
        }

        $validTypes = ['referrer', 'referrer_url', 'utm_source', 'utm_medium', 'utm_campaign', 'channel'];
        if (isset($rule['type']) && ! in_array($rule['type'], $validTypes, true)) {
            $errors[] = 'Invalid type. Valid types: ' . implode(', ', $validTypes);
        }

        return $errors;
    }

    /**
     * Match referrer URL against values.
     */
    private function matchReferrer(string $referrer, array $values, string $operator): bool
    {
        if ($referrer === '') {
            return $operator === 'not_contains' || $operator === 'not_in';
        }

        return match ($operator) {
            'contains' => $this->anyContains($referrer, $values),
            'not_contains' => ! $this->anyContains($referrer, $values),
            'in' => in_array($referrer, $values, true),
            'not_in' => ! in_array($referrer, $values, true),
            'domain' => $this->matchDomain($referrer, $values),
            default => false,
        };
    }

    /**
     * Match a single value against allowed values.
     */
    private function matchValue(?string $value, array $values, string $operator): bool
    {
        if ($value === null || $value === '') {
            return $operator === 'not_in';
        }

        return match ($operator) {
            'in', 'contains' => in_array($value, $values, true),
            'not_in', 'not_contains' => ! in_array($value, $values, true),
            default => false,
        };
    }

    /**
     * Match traffic channel.
     */
    private function matchChannel(string $referrer, ?string $utmSource, ?string $utmMedium, array $channels): bool
    {
        $detectedChannel = $this->detectChannel($referrer, $utmSource, $utmMedium);

        return in_array($detectedChannel, $channels, true);
    }

    /**
     * Detect traffic channel from referrer and UTM params.
     */
    private function detectChannel(string $referrer, ?string $utmSource, ?string $utmMedium): string
    {
        // Check UTM medium first
        if ($utmMedium !== null) {
            if (in_array($utmMedium, ['cpc', 'ppc', 'paid', 'paid_search', 'paid_social'], true)) {
                return 'paid';
            }
            if (in_array($utmMedium, ['email', 'newsletter'], true)) {
                return 'email';
            }
            if (in_array($utmMedium, ['social', 'social-media'], true)) {
                return 'social';
            }
            if (in_array($utmMedium, ['affiliate', 'referral'], true)) {
                return 'affiliate';
            }
        }

        // Check referrer domain
        if ($referrer === '') {
            return 'direct';
        }

        $socialDomains = ['facebook.com', 'twitter.com', 'instagram.com', 'linkedin.com', 'pinterest.com', 'tiktok.com', 'youtube.com'];
        $searchEngines = ['google.', 'bing.com', 'yahoo.com', 'duckduckgo.com', 'baidu.com'];

        foreach ($socialDomains as $domain) {
            if (str_contains($referrer, $domain)) {
                return 'social';
            }
        }

        foreach ($searchEngines as $engine) {
            if (str_contains($referrer, $engine)) {
                return 'organic';
            }
        }

        return 'referral';
    }

    /**
     * Check if referrer contains any of the values.
     *
     * @param  array<string>  $values
     */
    private function anyContains(string $referrer, array $values): bool
    {
        foreach ($values as $value) {
            if (str_contains($referrer, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match referrer domain against allowed domains.
     *
     * @param  array<string>  $domains
     */
    private function matchDomain(string $referrer, array $domains): bool
    {
        $parsedUrl = parse_url($referrer);
        $host = $parsedUrl['host'] ?? '';

        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract UTM source from request query params.
     */
    private function extractUtmSource(mixed $request): ?string
    {
        if ($request === null) {
            return null;
        }

        if (method_exists($request, 'query')) {
            return $request->query('utm_source');
        }

        return null;
    }
}
