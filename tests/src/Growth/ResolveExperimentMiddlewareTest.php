<?php

declare(strict_types=1);

require_once __DIR__ . '/PresentationTestSupport.php';

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;
use AIArmada\Growth\Http\Middleware\ResolveExperiment;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Growth\Support\ExperimentContextManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

it('stores experiment assignment context on the request from middleware', function (): void {
    config()->set('growth.features.experiment_middleware.enabled', true);

    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $request = Request::create('/sales-page', 'GET');
    $request->cookies->set('visitor_id', 'visitor-' . Str::lower(Str::random(8)));

    growthPresentationBindRequest($request, $owner);

    $response = app(ResolveExperiment::class)->handle(
        $request,
        function (Request $request): Response {
            $context = experiment();

            expect($request->attributes->get(ExperimentContextManager::EXPERIMENT_ATTRIBUTE))->toBeInstanceOf(Experiment::class)
                ->and($request->attributes->get(ExperimentContextManager::VARIANT_ATTRIBUTE))->toBeInstanceOf(Variant::class)
                ->and($request->attributes->get(ExperimentContextManager::ASSIGNMENT_ATTRIBUTE))->toBeInstanceOf(Assignment::class)
                ->and($context)->not->toBeNull();

            return response()->json([
                'variant_code' => $context?->variantCode(),
                'experiment_slug' => $context?->experimentSlug(),
            ]);
        },
        $experiment->slug,
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toContain($experiment->slug);
});

it('reuses an existing assignment when identity and session resolve from the request', function (): void {
    config()->set('growth.features.experiment_middleware.enabled', true);

    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $request = Request::create('/sales-page', 'GET');
    $request->setUserResolver(fn () => $owner);
    $sessionIdentifier = growthPresentationAttachStartedSession($request);

    growthPresentationBindRequest($request, $owner);

    [$identity, $session, $existingAssignment] = OwnerContext::withOwner($owner, function () use ($experiment, $owner, $sessionIdentifier): array {
        $identity = growthPresentationCreateIdentityForUser($experiment->trackedProperty, $owner);
        $session = growthPresentationCreateSessionForIdentifier($experiment->trackedProperty, $owner, $sessionIdentifier, $identity);
        $assignment = app(ResolveExperimentAssignment::class)->handle($experiment, $identity, $session);

        return [$identity, $session, $assignment];
    });

    expect($identity)->not->toBeNull()
        ->and($session)->not->toBeNull();

    $response = app(ResolveExperiment::class)->handle(
        $request,
        static function (): Response {
            return response()->json([
                'assignment_id' => experiment()?->assignmentId(),
                'variant_code' => experiment()?->variantCode(),
            ]);
        },
        $experiment->slug,
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toContain((string) $existingAssignment->getKey());
});

it('rejects experiment middleware resolution outside the current owner scope', function (): void {
    config()->set('growth.features.experiment_middleware.enabled', true);

    $owner = growthPresentationCreateOwner();
    $otherOwner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $request = Request::create('/sales-page', 'GET');
    $request->cookies->set('visitor_id', 'outsider-' . Str::lower(Str::random(8)));

    growthPresentationBindRequest($request, $otherOwner);

    expect(fn (): Response => app(ResolveExperiment::class)->handle(
        $request,
        static fn (): Response => response('ok'),
        $experiment->slug,
    ))->toThrow(AuthorizationException::class, 'Growth experiment is not accessible in the current owner scope.');
});

it('throws a clear exception when experiment slug is missing', function (): void {
    config()->set('growth.features.experiment_middleware.enabled', true);

    $request = Request::create('/sales-page', 'GET');

    growthPresentationBindRequest($request, growthPresentationCreateOwner());

    expect(fn (): Response => app(ResolveExperiment::class)->handle(
        $request,
        static fn (): Response => response('ok'),
    ))->toThrow(\InvalidArgumentException::class, 'Growth experiment slug is required.');
});

it('throws a clear exception for invalid anonymous id source configuration', function (): void {
    config()->set('growth.features.experiment_middleware.enabled', true);
    config()->set('growth.http.experiment_middleware.anonymous_id_source', 'cookies');

    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $request = Request::create('/sales-page', 'GET');

    growthPresentationBindRequest($request, $owner);

    expect(fn (): Response => app(ResolveExperiment::class)->handle(
        $request,
        static fn (): Response => response('ok'),
        $experiment->slug,
    ))->toThrow(\InvalidArgumentException::class, 'Invalid growth http.experiment_middleware.anonymous_id_source [cookies]. Supported values: cookie, header.');
});

it('throws a clear exception for invalid session identifier source configuration', function (): void {
    config()->set('growth.features.experiment_middleware.enabled', true);
    config()->set('growth.http.experiment_middleware.session_identifier_source', 'sessions');

    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $request = Request::create('/sales-page', 'GET');

    growthPresentationBindRequest($request, $owner);

    expect(fn (): Response => app(ResolveExperiment::class)->handle(
        $request,
        static fn (): Response => response('ok'),
        $experiment->slug,
    ))->toThrow(\InvalidArgumentException::class, 'Invalid growth http.experiment_middleware.session_identifier_source [sessions]. Supported values: laravel, cookie, header.');
});

it('throws a clear exception for empty anonymous id key when using cookie source', function (): void {
    config()->set('growth.features.experiment_middleware.enabled', true);
    config()->set('growth.http.experiment_middleware.anonymous_id_source', 'cookie');
    config()->set('growth.http.experiment_middleware.anonymous_id_key', '   ');

    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $request = Request::create('/sales-page', 'GET');

    growthPresentationBindRequest($request, $owner);

    expect(fn (): Response => app(ResolveExperiment::class)->handle(
        $request,
        static fn (): Response => response('ok'),
        $experiment->slug,
    ))->toThrow(\InvalidArgumentException::class, 'Invalid growth http.experiment_middleware.anonymous_id_key. Value cannot be empty.');
});

it('throws a clear exception for empty session identifier key when using header source', function (): void {
    config()->set('growth.features.experiment_middleware.enabled', true);
    config()->set('growth.http.experiment_middleware.session_identifier_source', 'header');
    config()->set('growth.http.experiment_middleware.session_identifier_key', '');

    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $request = Request::create('/sales-page', 'GET');

    growthPresentationBindRequest($request, $owner);

    expect(fn (): Response => app(ResolveExperiment::class)->handle(
        $request,
        static fn (): Response => response('ok'),
        $experiment->slug,
    ))->toThrow(\InvalidArgumentException::class, 'Invalid growth http.experiment_middleware.session_identifier_key. Value cannot be empty.');
});

it('returns authorization exception when slug is not readable and owner scopes are disabled', function (): void {
    config()->set('growth.features.owner.enabled', false);
    config()->set('signals.owner.enabled', false);

    expect(fn (): mixed => app(\AIArmada\Growth\Actions\ResolveReadableExperimentBySlug::class)->handle('missing-slug'))
        ->toThrow(AuthorizationException::class, 'Growth experiment is not accessible in the current owner scope.');
});