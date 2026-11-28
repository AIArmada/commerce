<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Delivery\Models\DocEmail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

final readonly class TrackDocEmail
{
    /**
     * Generate a tracking pixel URL.
     */
    public function getTrackingPixelUrl(DocEmail $email): string
    {
        $token = $this->generateTrackingToken($email, 'open');

        return route('docs.track.open', ['token' => $token]);
    }

    /**
     * Generate a tracked link URL.
     */
    public function getTrackedLinkUrl(DocEmail $email, string $url): string
    {
        $token = $this->generateTrackingToken($email, 'click', $url);

        return route('docs.track.click', ['token' => $token]);
    }

    /**
     * Mark an email as opened via tracking.
     */
    public function trackOpen(string $token): bool
    {
        $data = $this->decodeTrackingToken($token);

        if (! $data || $data['type'] !== 'open') {
            return false;
        }

        return (new DocEmail)->getConnection()->transaction(function () use ($data): bool {
            $email = DocEmail::query()->withoutOwnerScope()->lockForUpdate()->find($data['email_id']);

            if (! $email instanceof DocEmail || ! $this->matchesDocumentOwner($email)) {
                return false;
            }

            try {
                $owner = OwnerContext::fromTypeAndIdOrFail($email->owner_type, $email->owner_id);
            } catch (Throwable) {
                return false;
            }

            OwnerContext::withOwner($owner, static function () use ($email): void {
                $email->open_count = (int) $email->open_count + 1;
                $email->opened_at ??= CarbonImmutable::now();
                $email->save();
            });

            return true;
        });
    }

    /**
     * Mark an email link as clicked via tracking.
     */
    public function trackClick(string $token): ?string
    {
        $data = $this->decodeTrackingToken($token);

        if (! $data || $data['type'] !== 'click') {
            return null;
        }

        $url = $this->safeRedirectUrl($data['url']);

        if ($url === null) {
            return null;
        }

        return (new DocEmail)->getConnection()->transaction(function () use ($data, $url): ?string {
            $email = DocEmail::query()->withoutOwnerScope()->lockForUpdate()->find($data['email_id']);

            if (! $email instanceof DocEmail || ! $this->matchesDocumentOwner($email)) {
                return null;
            }

            try {
                $owner = OwnerContext::fromTypeAndIdOrFail($email->owner_type, $email->owner_id);
            } catch (Throwable) {
                return null;
            }

            OwnerContext::withOwner($owner, static function () use ($email): void {
                $email->click_count = (int) $email->click_count + 1;
                $email->clicked_at ??= CarbonImmutable::now();
                $email->save();
            });

            return $url;
        });
    }

    private function safeRedirectUrl(string $url): ?string
    {
        $candidate = mb_trim($url);

        if (
            $candidate === ''
            || str_starts_with($candidate, '//')
            || str_contains($candidate, '\\')
            || preg_match('/[\x00-\x20\x7f]/', $candidate) === 1
        ) {
            return null;
        }

        if (str_starts_with($candidate, '/')) {
            return $candidate;
        }

        if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = mb_strtolower((string) parse_url($candidate, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $candidate;
    }

    private function matchesDocumentOwner(DocEmail $email): bool
    {
        $doc = Doc::query()->withoutOwnerScope()->find($email->doc_id, ['id', 'owner_type', 'owner_id']);

        return $doc instanceof Doc
            && $doc->owner_type === $email->owner_type
            && (string) $doc->owner_id === (string) $email->owner_id;
    }

    /**
     * Generate a tracking token.
     */
    private function generateTrackingToken(DocEmail $email, string $type, ?string $url = null): string
    {
        $data = [
            'email_id' => $email->id,
            'type' => $type,
            'url' => $url,
            'issued_at' => CarbonImmutable::now()->getTimestamp(),
        ];

        $payload = json_encode($data);

        if ($payload === false) {
            return '';
        }

        return Crypt::encryptString($payload);
    }

    /**
     * Decode a tracking token.
     *
     * @return array<string, mixed>|null
     */
    private function decodeTrackingToken(string $token): ?array
    {
        try {
            $payload = Crypt::decryptString($token);

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($payload, true);

            if (! is_array($decoded)
                || ! is_string($decoded['email_id'] ?? null)
                || ! Str::isUuid($decoded['email_id'])
                || ! in_array($decoded['type'] ?? null, ['open', 'click'], true)
                || ($decoded['type'] === 'click' && (! is_string($decoded['url'] ?? null) || $decoded['url'] === ''))) {
                return null;
            }

            $issuedAt = $decoded['issued_at'] ?? null;

            if (! is_int($issuedAt) || $issuedAt <= 0) {
                return null;
            }

            $ttlDays = max(1, (int) config('docs.email.tracking.ttl_days', 180));

            $now = CarbonImmutable::now();
            if ($issuedAt > $now->getTimestamp() || $issuedAt < $now->subDays($ttlDays)->getTimestamp()) {
                return null;
            }

            return $decoded;
        } catch (Throwable) {
            return null;
        }
    }
}
