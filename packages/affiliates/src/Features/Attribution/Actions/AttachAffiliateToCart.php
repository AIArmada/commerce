<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Actions;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Events\AffiliateAttributed;
use AIArmada\Affiliates\Exceptions\AffiliateNotFoundException;
use AIArmada\Affiliates\Features\Attribution\Api\CartAttribution;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Features\Attribution\Queries\ReadAttributions;
use AIArmada\Affiliates\Features\Webhooks\Api\WebhookApi;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfile;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Support\IpHasher;
use AIArmada\Cart\Cart;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LogicException;

final readonly class AttachAffiliateToCart implements CartAttribution
{
    public function __construct(
        private Dispatcher $events,
        private WebhookApi $webhooks,
        private ReadAttributions $affiliateLookup,
        private DeleteAttributions $removal,
    ) {}

    public function attach(string $affiliateId, Cart $cart, array $context = []): ?AffiliateAttributionData
    {
        $connection = (new AffiliateAttribution)->getConnection();
        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($connection));

        if ($connection !== (new AffiliateTouchpoint)->getConnection()) {
            throw new LogicException('Cart attribution requires a shared database connection.');
        }

        return $connection->transaction(function () use ($affiliateId, $cart, $context, $connection): ?AffiliateAttributionData {
            $affiliate = app(AffiliateProfileReader::class)->lock($affiliateId, spl_object_id($connection))
                ?? throw new ModelNotFoundException('Affiliate not found.');

            return $this->attachLocked($affiliate, $cart, $context);
        }, attempts: 3);
    }

    public function attachFromCookie(Cart $cart, string $cookieValue, array $context = []): ?AffiliateAttributionData
    {
        $attribution = $this->affiliateLookup->findActiveAttributionByCookie($cookieValue);

        if ($attribution === null) {
            return null;
        }

        $affiliate = app(AffiliateProfileReader::class)->find($attribution->affiliate_id, spl_object_id($attribution->getConnection()));

        if ($affiliate === null || ! $affiliate->canBeAttributed
            || $affiliate->reference->ownerType !== $attribution->owner_type
            || $affiliate->reference->ownerId !== $attribution->owner_id) {
            return null;
        }

        $context = array_merge([
            'cookie_value' => $cookieValue,
            'source' => $context['source'] ?? $attribution->source,
            'medium' => $context['medium'] ?? $attribution->medium,
            'campaign' => $context['campaign'] ?? $attribution->campaign,
            'term' => $context['term'] ?? $attribution->term,
            'content' => $context['content'] ?? $attribution->content,
            'landing_url' => $context['landing_url'] ?? $attribution->landing_url,
            'referrer_url' => $context['referrer_url'] ?? $attribution->referrer_url,
        ], $context);

        return $this->attach($affiliate->reference->id, $cart, $context);
    }

    public function detach(Cart $cart): void
    {
        AffiliateAttribution::query()->forOwner()
            ->where('cart_identifier', $cart->getIdentifier())
            ->where('cart_instance', $cart->instance())
            ->active()
            ->update(['expires_at' => CarbonImmutable::now()]);
    }

    private function attachLocked(AffiliateProfile $affiliate, Cart $cart, array $context): ?AffiliateAttributionData
    {
        if (! $affiliate->canBeAttributed) {
            throw new AffiliateNotFoundException("Affiliate {$affiliate->code} cannot receive attribution.");
        }

        if ($this->isSelfReferral($affiliate)) {
            return null;
        }

        $identifier = $cart->getIdentifier();
        $instance = $cart->instance();

        $attributionQuery = AffiliateAttribution::query()
            ->where('affiliate_id', $affiliate->reference->id)
            ->where('cart_identifier', $identifier)
            ->where('cart_instance', $instance);

        $this->applyOwnerScope($attributionQuery);

        $attribution = $attributionQuery->lockForUpdate()->first();

        $payload = $this->buildAttributionPayload($affiliate, $cart, $context);
        $referredAt = CarbonImmutable::now();

        if (! $attribution && isset($payload['cookie_value'])) {
            $attribution = $this->findAttributionByCookie((string) $payload['cookie_value']);
        }

        if ($attribution && ($attribution->owner_type !== $affiliate->reference->ownerType || (string) $attribution->owner_id !== (string) $affiliate->reference->ownerId)) {
            $attribution = null;
        }

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
        $attribution->last_seen_at = $referredAt;
        if (isset($payload['cookie_value'])) {
            $attribution->last_cookie_seen_at = $referredAt;
        }
        $attribution->expires_at = $payload['expires_at'];
        $attribution->save();
        $this->recordTouchpoint($attribution, $affiliate, $payload);
        $this->pruneAttributionOverflow($identifier, $instance, $affiliate->reference->ownerType, $affiliate->reference->ownerId);

        if ($this->shouldDispatch('dispatch_attributed')) {
            $this->events?->dispatch(
                new AffiliateAttributed(
                    $affiliate->data,
                    $attribution->toData()
                )
            );
        }

        $attributionData = $attribution->toData();

        if ($this->shouldDispatch('dispatch_webhooks')) {
            $this->webhooks->dispatch('attribution', $attributionData->toArray());
        }

        return $attributionData;
    }

    private function buildAttributionPayload(AffiliateProfile $affiliate, ?Cart $cart, array $context): array
    {
        $expiresAt = null;
        $ttl = (int) config('affiliates.tracking.attribution_ttl_days', 30);

        if ($ttl > 0) {
            $expiresAt = CarbonImmutable::now()->addDays($ttl);
        }

        $cartIdentifier = $cart?->getIdentifier() ?? ($context['cart_identifier'] ?? null);
        $cartInstance = $cart?->instance() ?? ($context['cart_instance'] ?? null);
        $subjectType = $context['subject_type'] ?? null;
        $subjectKey = $context['subject_key'] ?? $cartIdentifier;
        $subjectId = $context['subject_id'] ?? null;
        $subjectInstance = $context['subject_instance'] ?? $cartInstance;
        $subjectTitleSnapshot = $this->normalizeSubjectTitleSnapshot(
            $context['subject_title_snapshot'] ?? null
        );

        if (! $cartInstance && $cart) {
            $cartInstance = 'default';
        }

        if (! $subjectInstance && $cart) {
            $subjectInstance = 'default';
        }

        return [
            'affiliate_id' => $affiliate->reference->id,
            'affiliate_code' => $affiliate->code,
            'subject_type' => $subjectType,
            'subject_key' => $subjectKey,
            'subject_id' => $subjectId,
            'subject_instance' => $subjectInstance,
            'subject_title_snapshot' => $subjectTitleSnapshot,
            'cart_identifier' => $cartIdentifier,
            'cart_instance' => $cartInstance,
            'affiliate_link_id' => $context['affiliate_link_id'] ?? null,
            'affiliate_program_id' => $context['affiliate_program_id'] ?? null,
            'attribution_type' => $context['attribution_type'] ?? null,
            'visitor_key' => $context['visitor_key'] ?? null,
            'channel' => $context['channel'] ?? null,
            'origin' => $context['origin'] ?? null,
            'sharer_user_id' => $context['sharer_user_id'] ?? null,
            'fingerprint' => $context['fingerprint'] ?? null,
            'cookie_value' => $context['cookie_value'] ?? null,
            'voucher_code' => $context['voucher_code'] ?? null,
            'commission_override' => $context['commission_override'] ?? null,
            'upline_levels' => $context['upline_levels'] ?? null,
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
            'metadata' => $this->mergeMetadata($context),
            'owner_type' => $affiliate->reference->ownerType,
            'owner_id' => $affiliate->reference->ownerId,
            'expires_at' => $expiresAt,
        ];
    }

    private function recordTouchpoint(
        AffiliateAttribution $attribution,
        AffiliateProfile $affiliate,
        array $payload
    ): void {
        $touchpoint = new AffiliateTouchpoint([
            'affiliate_attribution_id' => $attribution->getKey(),
            'affiliate_id' => $affiliate->reference->id,
            'affiliate_code' => $affiliate->code,
            'subject_type' => $payload['subject_type'] ?? $attribution->subject_type,
            'subject_key' => $payload['subject_key'] ?? $attribution->subject_key,
            'subject_id' => $payload['subject_id'] ?? $attribution->subject_id,
            'subject_instance' => $payload['subject_instance'] ?? $attribution->subject_instance,
            'subject_title_snapshot' => $payload['subject_title_snapshot'] ?? $attribution->subject_title_snapshot,
            'affiliate_link_id' => $payload['affiliate_link_id'] ?? $attribution->affiliate_link_id,
            'visitor_key' => $payload['visitor_key'] ?? $attribution->visitor_key,
            'channel' => $payload['channel'] ?? $attribution->channel,
            'origin' => $payload['origin'] ?? $attribution->origin,
            'source' => $payload['source'] ?? null,
            'medium' => $payload['medium'] ?? null,
            'campaign' => $payload['campaign'] ?? null,
            'term' => $payload['term'] ?? null,
            'content' => $payload['content'] ?? null,
            'metadata' => [
                'utm' => [
                    'source' => $payload['source'] ?? null,
                    'medium' => $payload['medium'] ?? null,
                    'campaign' => $payload['campaign'] ?? null,
                    'term' => $payload['term'] ?? null,
                    'content' => $payload['content'] ?? null,
                ],
            ],
            'touched_at' => CarbonImmutable::now(),
        ]);
        $touchpoint->forceFill([
            'owner_type' => $attribution->owner_type ?? $affiliate->reference->ownerType,
            'owner_id' => $attribution->owner_id ?? $affiliate->reference->ownerId,
        ]);
        $touchpoint->save();
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
        $cookieCandidates = $this->resolveCookieCandidates($cookieValue);

        if ($cookieCandidates === []) {
            return null;
        }

        $query = AffiliateAttribution::query()
            ->whereIn('cookie_value', $cookieCandidates)
            ->active()
            ->latest('last_cookie_seen_at');

        $this->applyOwnerScope($query);

        return $query->lockForUpdate()->first();
    }

    private function resolveCookieCandidates(?string $cookieValue): array
    {
        if (! is_string($cookieValue) || $cookieValue === '') {
            return [];
        }

        $candidates = [$cookieValue];

        try {
            $decryptedCookieValue = decrypt($cookieValue);

            if (is_string($decryptedCookieValue) && $decryptedCookieValue !== '') {
                $candidates[] = $decryptedCookieValue;
            }
        } catch (DecryptException) {
        }

        return array_values(array_unique($candidates));
    }

    private function pruneAttributionOverflow(
        ?string $cartIdentifier,
        ?string $cartInstance,
        ?string $ownerType,
        ?string $ownerId
    ): void {
        $max = (int) config('affiliates.tracking.max_attributions_per_identifier', 0);

        if ($max <= 0 || ! $cartIdentifier) {
            return;
        }

        $query = AffiliateAttribution::query()
            ->where('cart_identifier', $cartIdentifier)
            ->when($cartInstance, static fn (Builder $builder, string $instance): Builder => $builder->where(
                'cart_instance',
                $instance
            ));

        if (config('affiliates.owner.enabled', false)) {
            if ($ownerType && $ownerId) {
                $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
            } else {
                $query->whereNull('owner_type')->whereNull('owner_id');
            }
        }

        $excess = (clone $query)->count() - $max;

        if ($excess <= 0) {
            return;
        }

        $toDelete = (clone $query)
            ->reorder()
            ->orderByRaw('CASE WHEN last_seen_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_seen_at')
            ->orderBy('id')
            ->limit($excess)
            ->pluck('id')
            ->all();

        $this->removal->deleteMany(array_map('strval', $toDelete));
    }

    private function isSelfReferral(AffiliateProfile $affiliate): bool
    {
        if (! config('affiliates.tracking.block_self_referral', false)) {
            return false;
        }

        $owner = $this->resolveOwner();

        if (! $owner || ! $affiliate->reference->ownerId || ! $affiliate->reference->ownerType) {
            return false;
        }

        return $owner->getMorphClass() === $affiliate->reference->ownerType
            && (string) $owner->getKey() === (string) $affiliate->reference->ownerId;
    }

    private function applyOwnerScope(Builder $query): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $owner = $this->resolveOwner();
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return OwnerQuery::applyToEloquentBuilder($query, $owner, false);
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

    private function mergeMetadata(array $context): array
    {
        $metadata = $context['metadata'] ?? Arr::only($context, ['coupon', 'notes', 'utm']);

        return Arr::except($metadata, [
            'affiliate_id', 'affiliate_code', 'affiliate_attribution_id',
            'subject_type', 'subject_key', 'subject_id', 'subject_instance',
            'subject_title_snapshot', 'origin', 'voucher_code', 'program_id',
            'affiliate_program_id', 'commission_override', 'upline_levels',
        ]);
    }

    private function normalizeSubjectTitleSnapshot(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Str::limit($value, 200, '');
    }

    private function shouldDispatch(string $flag): bool
    {
        return (bool) config("affiliates.events.{$flag}", true);
    }
}
