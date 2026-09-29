<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Organizations\OrganizationsTestCase;
use AIArmada\Organizations\Http\Middleware\CurrentOrganizationMiddleware;
use Illuminate\Routing\Router;

uses(OrganizationsTestCase::class);

it('registers the current.organization middleware alias', function (): void {
    $middleware = app(Router::class)->getMiddleware();

    expect($middleware)->toHaveKey('current.organization');
    expect($middleware['current.organization'])->toBe(CurrentOrganizationMiddleware::class);
});
