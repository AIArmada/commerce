<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Contracts\BotDetectorInterface;
use AIArmada\Links\Contracts\UserAgentParserInterface;
use AIArmada\Links\Events\LinkClicked;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;
use AIArmada\Links\Support\LinkAttributes;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

final class RecordLinkClick
{
    use AsAction;

    public function __construct(
        private readonly UserAgentParserInterface $userAgentParser,
        private readonly BotDetectorInterface $botDetector,
    ) {}

    /**
     * @param  array<string, mixed>  $context  Explicit click context. Missing values fall back to the current request.
     */
    public function handle(Link $link, array $context = []): ?LinkClick
    {
        $userAgent = $this->stringValue($context['user_agent'] ?? null) ?? $this->requestUserAgent();
        $parsed = $this->userAgentParser->parse($userAgent);
        $isBot = ($parsed['is_bot'] ?? false) || $this->botDetector->isBot($userAgent);

        if ($isBot && ! (bool) config('links.features.tracking.bots.record', true)) {
            return null;
        }

        $click = new LinkClick([
            'link_id' => $link->getKey(),
            'occurred_at' => CarbonImmutable::now(),
            'ip_address' => $this->resolveIpAddress($context),
            'user_agent' => (bool) config('links.features.tracking.user_agent.store_raw', true) ? $userAgent : null,
            'device_type' => $parsed['device_type'] ?? null,
            'device_brand' => $parsed['device_brand'] ?? null,
            'device_model' => $parsed['device_model'] ?? null,
            'browser' => $parsed['browser'] ?? null,
            'browser_version' => $parsed['browser_version'] ?? null,
            'os' => $parsed['os'] ?? null,
            'os_version' => $parsed['os_version'] ?? null,
            'is_bot' => $isBot,
            'referrer' => $this->stringValue($context['referrer'] ?? null) ?? $this->requestReferrer(),
            'utm_source' => $this->utmValue($context, 'utm_source'),
            'utm_medium' => $this->utmValue($context, 'utm_medium'),
            'utm_campaign' => $this->utmValue($context, 'utm_campaign'),
            'utm_content' => $this->utmValue($context, 'utm_content'),
            'utm_term' => $this->utmValue($context, 'utm_term'),
            'properties' => $this->propertiesValue($context['properties'] ?? null),
            'owner_type' => $link->owner_type,
            'owner_id' => $link->owner_id,
        ]);

        $owner = OwnerContext::fromTypeAndId($link->owner_type, $link->owner_id);

        OwnerContext::withOwner($owner, static function () use ($click, $link, $isBot): void {
            $click->save();

            $link->increment('total_clicks');

            if (! $isBot || (bool) config('links.features.tracking.bots.count', false)) {
                $link->increment('human_clicks');
            }

            $link->update([
                'first_clicked_at' => $link->first_clicked_at ?? $click->occurred_at,
                'last_clicked_at' => $click->occurred_at,
            ]);
        });

        event(new LinkClicked($link, $click));

        return $click;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveIpAddress(array $context): ?string
    {
        if (! (bool) config('links.features.tracking.ip.enabled', true)) {
            return null;
        }

        $ip = $this->stringValue($context['ip_address'] ?? null);

        if ($ip === null && app()->bound('request')) {
            $ip = request()->ip();
        }

        if (! is_string($ip) || $ip === '') {
            return null;
        }

        return (bool) config('links.features.tracking.ip.anonymize', false)
            ? $this->anonymizeIp($ip)
            : $ip;
    }

    private function anonymizeIp(string $ip): string
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return $ip;
        }

        if (mb_strlen($packed, '8bit') === 4) {
            $packed[3] = "\x00";
        } else {
            for ($i = 6; $i < 16; $i++) {
                $packed[$i] = "\x00";
            }
        }

        $anonymized = inet_ntop($packed);

        return $anonymized === false ? $ip : $anonymized;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function utmValue(array $context, string $key): ?string
    {
        $explicit = $this->stringValue($context[$key] ?? null);

        if ($explicit !== null) {
            return $explicit;
        }

        if (! in_array($key, LinkAttributes::UTM_KEYS, true)) {
            return null;
        }

        if (app()->bound('request')) {
            $fromRequest = request()->query($key);

            if (is_string($fromRequest) && $fromRequest !== '') {
                return $fromRequest;
            }
        }

        return null;
    }

    private function requestUserAgent(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return $this->stringValue(request()->userAgent());
    }

    private function requestReferrer(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return $this->stringValue(request()->headers->get('referer'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function propertiesValue(mixed $value): ?array
    {
        return is_array($value) && $value !== [] ? $value : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return $value;
    }
}
