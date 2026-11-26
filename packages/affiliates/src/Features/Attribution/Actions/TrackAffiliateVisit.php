<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Actions;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionTracking;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Features\Attribution\Queries\ReadAttributions;
use AIArmada\Affiliates\Features\Fraud\Api\FraudApi;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateDirectory;
use AIArmada\Affiliates\Support\IpHasher;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;

final readonly class TrackAffiliateVisit implements AttributionTracking
{
    public function __construct(
        private AffiliateDirectory $affiliateLookup,
        private ReadAttributions $attributions,
        private FraudApi $fraud,
    ) {}

    public function track(string $code, array $context = [], ?string $cookieValue = null): ?AffiliateAttributionData
    {
        $identity = $this->affiliateLookup->findByCode($code);

        if ($identity === null || ! $identity->canBeAttributed) {
            return null;
        }

        $affiliate = Affiliate::query()->forOwner()->find($identity->id);

        if ($affiliate === null || ! $affiliate->canBeAttributed()) {
            return null;
        }

        if ($this->isRateLimited($affiliate, $context)) {
            return null;
        }

        if ($this->isSelfReferral($affiliate)) {
            return null;
        }

        if ($this->isFingerprintBlocked($affiliate, $context)) {
            return null;
        }

        if (! $this->fraud->analyzeClick((string) $affiliate->getKey(), is_string($context['ip_address'] ?? null) ? $context['ip_address'] : null, is_string($context['user_agent'] ?? null) ? $context['user_agent'] : null)->allowed) {
            return null;
        }

        $connection = $affiliate->getConnection();

        if ($connection !== (new AffiliateAttribution)->getConnection()) {
            throw new LogicException('Attribution tracking requires a shared database connection.');
        }

        return $connection->transaction(function () use ($affiliate, $context, $cookieValue): ?AffiliateAttributionData {
            $locked = Affiliate::query()->forOwner()->lockForUpdate()->find($affiliate->getKey());

            if (! $locked instanceof Affiliate || ! $locked->canBeAttributed() || $this->isSelfReferral($locked) || $this->isFingerprintBlocked($locked, $context)) {
                return null;
            }

            return $this->storeCookieAttribution($locked, $context, $cookieValue)->toData();
        }, attempts: 3);
    }

    private function storeCookieAttribution(Affiliate $affiliate, array $context, ?string $cookieValue): AffiliateAttribution
    {
        $payload = $this->buildAttributionPayload($affiliate, $context);

        if (! $cookieValue) {
            $cookieValue = (string) Str::uuid();
        }

        $payload['cookie_value'] = $cookieValue;

        $candidate = $this->attributions->findActiveAttributionByCookie($cookieValue);
        $attribution = $candidate === null ? null : AffiliateAttribution::query()
            ->forOwner()
            ->where('cookie_value', $candidate->cookie_value)
            ->active()
            ->lockForUpdate()
            ->find($candidate->getKey());

        if ($candidate !== null && ($attribution === null
            || $attribution->owner_type !== $affiliate->owner_type
            || (string) $attribution->owner_id !== (string) $affiliate->owner_id
            || ! $this->fingerprintMatches($attribution, $payload['fingerprint'] ?? null))) {
            $cookieValue = (string) Str::uuid();
            $payload['cookie_value'] = $cookieValue;
            $attribution = null;
        } elseif ($attribution !== null) {
            $payload['cookie_value'] = $attribution->cookie_value;
        } elseif (AffiliateAttribution::query()->forOwner()->where('cookie_value', $cookieValue)->exists()) {
            $payload['cookie_value'] = (string) Str::uuid();
        }

        $referredAt = CarbonImmutable::now();

        if ($attribution) {
            $this->fillAttribution($attribution, $payload);
        } else {
            if (! isset($payload['cart_instance'])) {
                $payload['cart_instance'] = 'default';
            }

            $attribution = new AffiliateAttribution(Arr::except($payload, ['owner_type', 'owner_id']));
            $attribution->forceFill(Arr::only($payload, ['owner_type', 'owner_id']));
            $attribution->first_seen_at = $referredAt;
        }

        $attribution->last_referred_at = $referredAt;
        $attribution->last_cookie_seen_at = $referredAt;
        $attribution->last_seen_at = $referredAt;
        $attribution->expires_at = $payload['expires_at'];
        $attribution->save();

        return $attribution;
    }

    private function buildAttributionPayload(Affiliate $affiliate, array $context): array
    {
        $expiresAt = null;
        $ttl = (int) config('affiliates.tracking.attribution_ttl_days', 30);

        if ($ttl > 0) {
            $expiresAt = CarbonImmutable::now()->addDays($ttl);
        }

        $subjectType = $context['subject_type'] ?? null;
        $subjectTitleSnapshot = $this->normalizeSubjectTitleSnapshot(
            $context['subject_title_snapshot'] ?? null
        );

        return [
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'subject_type' => $subjectType,
            'subject_key' => $context['subject_key'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'subject_instance' => $context['subject_instance'] ?? 'default',
            'subject_title_snapshot' => $subjectTitleSnapshot,
            'cart_identifier' => $context['cart_identifier'] ?? null,
            'cart_instance' => $context['cart_instance'] ?? 'default',
            'cookie_value' => $context['cookie_value'] ?? null,
            'voucher_code' => $context['voucher_code'] ?? null,
            'source' => $context['source'] ?? $context['utm_source'] ?? null,
            'medium' => $context['medium'] ?? $context['utm_medium'] ?? null,
            'campaign' => $context['campaign'] ?? $context['utm_campaign'] ?? null,
            'term' => $context['term'] ?? $context['utm_term'] ?? null,
            'content' => $context['content'] ?? $context['utm_content'] ?? null,
            'landing_url' => $context['landing_url'] ?? null,
            'referrer_url' => $context['referrer_url'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'ip_address' => IpHasher::hash($context['ip_address'] ?? null),
            'user_id' => $context['user_id'] ?? $this->resolveUserId(),
            'visitor_key' => $context['visitor_key'] ?? null,
            'channel' => $context['channel'] ?? null,
            'origin' => $context['origin'] ?? null,
            'affiliate_link_id' => $context['affiliate_link_id'] ?? null,
            'attribution_type' => $context['attribution_type'] ?? null,
            'sharer_user_id' => $context['sharer_user_id'] ?? null,
            'fingerprint' => $this->resolveFingerprint($context),
            'metadata' => $this->mergeMetadata($context),
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
            'expires_at' => $expiresAt,
        ];
    }

    private function fillAttribution(AffiliateAttribution $attribution, array $payload): void
    {
        $nullableKeys = ['expires_at'];

        foreach ($payload as $key => $value) {
            if ($value === null && ! in_array($key, $nullableKeys, true)) {
                unset($payload[$key]);
            }
        }

        if ($payload !== []) {
            $attribution->fill($payload);
        }
    }

    private function applyOwnerScope(Builder $query): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $owner = $this->resolveOwner();
        $includeGlobal = (bool) config('affiliates.owner.include_global', false);

        return OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
    }

    private function resolveOwner(): ?Model
    {
        if (! config('affiliates.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    private function resolveUserId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $user = request()->user();

        if ($user && method_exists($user, 'getAuthIdentifier')) {
            $id = $user->getAuthIdentifier();

            return $id !== null ? (string) $id : null;
        }

        return null;
    }

    private function isRateLimited(Affiliate $affiliate, array $context): bool
    {
        $config = config('affiliates.tracking.ip_rate_limit', []);

        if (! ($config['enabled'] ?? false)) {
            return false;
        }

        $ip = $context['ip_address'] ?? (app()->bound('request') ? request()->ip() : null);

        if (! is_string($ip) || $ip === '') {
            return false;
        }

        $max = (int) ($config['max'] ?? 0);
        $decay = (int) ($config['decay_minutes'] ?? 1);

        if ($max <= 0) {
            return false;
        }

        $key = sprintf('affiliates:ip-rate:%s:%s', $affiliate->getKey(), IpHasher::hash($ip));

        if (Cache::store()->add($key, 1, CarbonImmutable::now()->addMinutes($decay))) {
            return false;
        }

        return (int) Cache::store()->increment($key) > $max;
    }

    private function fingerprintMatches(AffiliateAttribution $attribution, ?string $fingerprint): bool
    {
        if ($fingerprint === null || $attribution->fingerprint === null) {
            return true;
        }

        return hash_equals($attribution->fingerprint, $fingerprint);
    }

    private function isSelfReferral(Affiliate $affiliate): bool
    {
        if (! config('affiliates.tracking.block_self_referral', false)) {
            return false;
        }

        $owner = $this->resolveOwner();

        if (! $owner || ! $affiliate->owner_id || ! $affiliate->owner_type) {
            return false;
        }

        return $owner->getMorphClass() === $affiliate->owner_type
            && (string) $owner->getKey() === (string) $affiliate->owner_id;
    }

    private function isFingerprintBlocked(Affiliate $affiliate, array $context): bool
    {
        $fingerprintConfig = config('affiliates.tracking.fingerprint', []);

        if (! ($fingerprintConfig['enabled'] ?? false)) {
            return false;
        }

        $fingerprint = $this->resolveFingerprint($context);

        if (! $fingerprint) {
            return false;
        }

        if (! ($fingerprintConfig['block_duplicates'] ?? false)) {
            return false;
        }

        $query = AffiliateAttribution::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->where('fingerprint', $fingerprint)
            ->active();

        $this->applyOwnerScope($query);

        return $query->exists();
    }

    private function resolveFingerprint(array $context): ?string
    {
        $fingerprintConfig = config('affiliates.tracking.fingerprint', []);

        if (! ($fingerprintConfig['enabled'] ?? false)) {
            return null;
        }

        $ua = $context['user_agent'] ?? (app()->bound('request') ? request()->userAgent() : null);
        $ip = $context['ip_address'] ?? (app()->bound('request') ? request()->ip() : null);

        if (! $ua && ! $ip) {
            return null;
        }

        return hash('sha256', ($ua ?? '') . '|' . ($ip ?? ''));
    }

    private function normalizeSubjectTitleSnapshot(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Str::limit($value, 200, '');
    }

    private function mergeMetadata(array $context): array
    {
        $metadata = $context['metadata'] ?? [];

        if (! is_array($metadata)) {
            return [];
        }

        return array_diff_key($metadata, array_flip([
            'affiliate_id', 'affiliate_code', 'affiliate_attribution_id', 'affiliate_link_id',
            'subject_type', 'subject_key', 'subject_id', 'subject_instance',
            'subject_title_snapshot', 'voucher_code', 'source', 'medium', 'campaign',
            'term', 'content', 'landing_url', 'referrer_url', 'user_agent', 'ip_address',
            'user_id', 'visitor_key', 'channel', 'origin', 'sharer_user_id', 'fingerprint',
        ]));
    }
}
