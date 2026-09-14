<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

require_once __DIR__ . '/PresentationTestSupport.php';

use AIArmada\Growth\Http\Middleware\ResolveExperiment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

describe('Experiment middleware denial logging', function (): void {
    it('logs out-of-scope resolution denials while still failing closed', function (): void {
        config()->set('growth.features.experiment_middleware.enabled', true);

        $owner = \growthPresentationCreateOwner();
        $otherOwner = \growthPresentationCreateOwner();
        $experiment = \growthPresentationCreateExperiment($owner);
        $request = Request::create('/sales-page', 'GET');

        \growthPresentationBindRequest($request, $otherOwner);

        Log::spy();

        expect(fn (): Response => app(ResolveExperiment::class)->handle(
            $request,
            static fn (): Response => response('ok'),
            $experiment->slug,
        ))->toThrow(AuthorizationException::class, 'Growth experiment is not accessible in the current owner scope.');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'denied out-of-scope resolution')
                && ($context['slug'] ?? null) === $experiment->slug);
    });
});
