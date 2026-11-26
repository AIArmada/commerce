<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\Persistence;

use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Signals\Features\Ingestion\Models\SignalInteractionRule;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\AuthenticationException;

final readonly class ScanPreviewStore
{
    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $meta
     */
    public function put(array $candidates, array $meta): void
    {
        $key = $this->operatorKey();

        if ($key === null) {
            throw new AuthenticationException;
        }

        OwnerCache::put(
            OwnerUiScope::resolveOwner(SignalInteractionRule::class),
            $key,
            ['candidates' => $candidates, 'meta' => $meta],
            CarbonImmutable::now()->addMinutes(30),
        );
    }

    /** @return array{candidates: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function get(): array
    {
        $key = $this->operatorKey();

        if ($key === null) {
            return ['candidates' => [], 'meta' => []];
        }

        $payload = OwnerCache::get(OwnerUiScope::resolveOwner(SignalInteractionRule::class), $key);

        if (! is_array($payload)) {
            return ['candidates' => [], 'meta' => []];
        }

        /** @var list<array<string, mixed>> $candidates */
        $candidates = is_array($payload['candidates'] ?? null) ? array_values($payload['candidates']) : [];
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        return ['candidates' => $candidates, 'meta' => $meta];
    }

    public function forget(): void
    {
        $key = $this->operatorKey();

        if ($key !== null) {
            OwnerCache::forget(OwnerUiScope::resolveOwner(SignalInteractionRule::class), $key);
        }
    }

    private function operatorKey(): ?string
    {
        $guard = Filament::getCurrentPanel()?->getAuthGuard() ?? auth()->getDefaultDriver();
        $operator = auth()->guard($guard)->user();
        $identifier = $operator?->getAuthIdentifier();

        if ($operator === null || ! is_scalar($identifier) || (string) $identifier === '') {
            return null;
        }

        return 'filament-signals.interaction-rule-scan-preview.user-' . hash('sha256', $guard . '|' . $operator::class . '|' . (string) $identifier);
    }
}
