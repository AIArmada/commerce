<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

final class TrackAffiliateVisit
{
    use AsAction;

    public function handle(string $code, array $context = [], ?string $cookieValue = null): ?AffiliateAttributionData
    {
        $affiliate = Affiliate::query()
            ->when(
                config('affiliates.owner.enabled', false),
                fn (Builder $q) => $q->forOwner($this->resolveOwner()),
            )
            ->where('code', $code)
            ->first();

        if (! $affiliate || ! $affiliate->isActive()) {
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

        $attribution = $this->storeCookieAttribution($affiliate, $context, $cookieValue);

        return AffiliateAttributionData::fromModel($attribution);
    }

    private function storeCookieAttribution(Affiliate $affiliate, array $context, ?string $cookieValue): AffiliateAttribution
    {
        $payload = $this->buildAttributionPayload($affiliate, $context);

        if (! $cookieValue) {
            $cookieValue = (string) Str::uuid();
        }

        $payload['cookie_value'] = $cookieValue;

        $attribution = $this->findAttributionByCookie($cookieValue);

        if ($attribution) {
            $this->fillAttribution($attribution, $payload);
        } else {
            if (! isset($payload['cart_instance'])) {
                $payload['cart_instance'] = 'default';
            }

            $attribution = new AffiliateAttribution($payload);
            $attribution->first_seen_at = now();
        }

        $attribution->last_cookie_seen_at = now();
        $attribution->expires_at = $payload['expires_at'];
        $attribution->save();

        return $attribution;
    }

    private function buildAttributionPayload(Affiliate $affiliate, array $context): array
    {
        $expiresAt = null;
        $ttl = (int) config('affiliates.tracking.attribution_ttl_days', 30);

        if ($ttl > 0) {
            $expiresAt = now()->addDays($ttl);
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
            'ip_address' => $context['ip_address'] ?? null,
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

    private function findAttributionByCookie(?string $cookieValue): ?AffiliateAttribution
    {
        if (! is_string($cookieValue) || $cookieValue === '') {
            return null;
        }

        $candidates = [$cookieValue];

        try {
            $decrypted = decrypt($cookieValue);

            if ($decrypted !== '') {
                $candidates[] = $decrypted;
            }
        } catch (DecryptException) {
        }

        $candidates = array_values(array_unique($candidates));

        $query = AffiliateAttribution::query()
            ->with('affiliate')
            ->whereIn('cookie_value', $candidates)
            ->active()
            ->latest('last_cookie_seen_at');

        $this->applyOwnerScope($query);

        return $query->first();
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

        if (! $ip) {
            return false;
        }

        $max = (int) ($config['max'] ?? 0);
        $decay = (int) ($config['decay_minutes'] ?? 1);

        if ($max <= 0) {
            return false;
        }

        $key = sprintf('affiliates:ip-rate:%s:%s', $affiliate->code, $ip);

        $hits = (int) Cache::store()->increment($key);

        if ($hits === 1) {
            Cache::store()->put($key, $hits, now()->addMinutes($decay));
        }

        return $hits > $max;
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
            && $owner->getKey() === $affiliate->owner_id;
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
