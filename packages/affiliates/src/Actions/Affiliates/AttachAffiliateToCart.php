<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Events\AffiliateAttributed;
use AIArmada\Affiliates\Exceptions\AffiliateNotFoundException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use AIArmada\Cart\Cart;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

final class AttachAffiliateToCart
{
    use AsAction;

    public function __construct(
        private readonly Dispatcher $events,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    public function handle(Affiliate $affiliate, Cart $cart, array $context = []): ?AffiliateAttributionData
    {
        if (! $affiliate->isActive()) {
            throw new AffiliateNotFoundException("Affiliate {$affiliate->code} is not active.");
        }

        if ($this->isSelfReferral($affiliate)) {
            return null;
        }

        $identifier = $cart->getIdentifier();
        $instance = $cart->instance();

        $attributionQuery = AffiliateAttribution::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->where('cart_identifier', $identifier)
            ->where('cart_instance', $instance);

        $this->applyOwnerScope($attributionQuery);

        $attribution = $attributionQuery->first();

        $payload = $this->buildAttributionPayload($affiliate, $cart, $context);

        if (! $attribution && isset($payload['cookie_value'])) {
            $attribution = $this->findAttributionByCookie((string) $payload['cookie_value']);
        }

        if ($attribution) {
            $this->fillAttribution($attribution, $payload);
        } else {
            if (! isset($payload['cart_instance'])) {
                $payload['cart_instance'] = 'default';
            }

            $attribution = new AffiliateAttribution($payload);
            $attribution->first_seen_at = now();
        }

        $attribution->last_seen_at = now();
        if (isset($payload['cookie_value'])) {
            $attribution->last_cookie_seen_at = now();
        }
        $attribution->expires_at = $payload['expires_at'];
        $attribution->save();
        $this->recordTouchpoint($attribution, $affiliate, $payload);
        $this->pruneAttributionOverflow($identifier, $instance, $affiliate->owner_type, $affiliate->owner_id);

        if ($this->shouldDispatch('dispatch_attributed')) {
            $this->events?->dispatch(
                new AffiliateAttributed(
                    AffiliateData::fromModel($affiliate),
                    AffiliateAttributionData::fromModel($attribution)
                )
            );
        }

        $attributionData = AffiliateAttributionData::fromModel($attribution);

        if ($this->shouldDispatch('dispatch_webhooks')) {
            $this->webhooks->dispatch('attribution', $attributionData->toArray());
        }

        return $attributionData;
    }

    private function buildAttributionPayload(Affiliate $affiliate, ?Cart $cart, array $context): array
    {
        $expiresAt = null;
        $ttl = (int) config('affiliates.tracking.attribution_ttl_days', 30);

        if ($ttl > 0) {
            $expiresAt = now()->addDays($ttl);
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
            'affiliate_id' => $affiliate->getKey(),
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
            'ip_address' => $context['ip_address'] ?? null,
            'user_id' => $context['user_id'] ?? $this->resolveUserId(),
            'metadata' => $this->mergeMetadata($context),
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
            'expires_at' => $expiresAt,
        ];
    }

    private function recordTouchpoint(
        AffiliateAttribution $attribution,
        Affiliate $affiliate,
        array $payload
    ): void {
        AffiliateTouchpoint::create([
            'affiliate_attribution_id' => $attribution->getKey(),
            'affiliate_id' => $affiliate->getKey(),
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
            'owner_type' => $attribution->owner_type ?? $affiliate->owner_type,
            'owner_id' => $attribution->owner_id ?? $affiliate->owner_id,
            'metadata' => [
                'utm' => [
                    'source' => $payload['source'] ?? null,
                    'medium' => $payload['medium'] ?? null,
                    'campaign' => $payload['campaign'] ?? null,
                    'term' => $payload['term'] ?? null,
                    'content' => $payload['content'] ?? null,
                ],
            ],
            'touched_at' => now(),
        ]);
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
            ->with('affiliate')
            ->whereIn('cookie_value', $cookieCandidates)
            ->active()
            ->latest('last_cookie_seen_at');

        $this->applyOwnerScope($query);

        return $query->first();
    }

    private function resolveCookieCandidates(?string $cookieValue): array
    {
        if (! is_string($cookieValue) || $cookieValue === '') {
            return [];
        }

        $candidates = [$cookieValue];

        try {
            $decryptedCookieValue = decrypt($cookieValue);

            if ($decryptedCookieValue !== '') {
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
            ))
            ->orderByDesc('last_seen_at');

        if (config('affiliates.owner.enabled', false)) {
            if ($ownerType && $ownerId) {
                $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
            } else {
                $query->whereNull('owner_type')->whereNull('owner_id');
            }
        }

        $ids = $query->pluck('id');

        if ($ids->count() <= $max) {
            return;
        }

        $toDelete = $ids->slice($max)->all();

        if ($toDelete !== []) {
            AffiliateAttribution::query()
                ->whereIn('id', $toDelete)
                ->delete();
        }
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
