<?php

declare(strict_types=1);

namespace App\Checkout;

use AIArmada\Cart\Facades\Cart;
use AIArmada\Growth\Contracts\RequestExperimentSubjectResolver;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Support\Http\DefaultRequestExperimentSubjectResolver;
use AIArmada\Growth\Support\Request\RequestExperimentSubjects;
use Illuminate\Http\Request;
use Throwable;

final class DemoRequestExperimentSubjectResolver implements RequestExperimentSubjectResolver
{
    private const string STICKY_ANONYMOUS_ID_SESSION_KEY = 'demo_growth_checkout_subject';

    public function __construct(
        private readonly DefaultRequestExperimentSubjectResolver $resolver,
    ) {}

    public function resolve(Request $request, Experiment $experiment): RequestExperimentSubjects
    {
        $subjects = $this->resolver->resolve($request, $experiment);
        $stickyAnonymousId = $this->stickyAnonymousId($request);

        if ($stickyAnonymousId !== null) {
            return new RequestExperimentSubjects($subjects->identity, $subjects->session, $stickyAnonymousId);
        }

        if ($subjects->anonymousId !== null) {
            $this->rememberStickyAnonymousId($request, $subjects->anonymousId);

            return $subjects;
        }

        $fallbackAnonymousId = $this->fallbackAnonymousId($request);

        if ($fallbackAnonymousId === null) {
            return $subjects;
        }

        $this->rememberStickyAnonymousId($request, $fallbackAnonymousId);

        return new RequestExperimentSubjects($subjects->identity, $subjects->session, $fallbackAnonymousId);
    }

    private function stickyAnonymousId(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        return $this->normalizeString($request->session()->get(self::STICKY_ANONYMOUS_ID_SESSION_KEY));
    }

    private function rememberStickyAnonymousId(Request $request, string $anonymousId): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->put(self::STICKY_ANONYMOUS_ID_SESSION_KEY, $anonymousId);
    }

    private function fallbackAnonymousId(Request $request): ?string
    {
        try {
            $cartIdentifier = $this->normalizeString(Cart::getIdentifier());

            if ($cartIdentifier !== null) {
                return $cartIdentifier;
            }
        } catch (Throwable) {
        }

        if (! $request->hasSession()) {
            return null;
        }

        return $this->normalizeString($request->session()->getId());
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = mb_trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
