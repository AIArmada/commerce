<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Support\AuthzScopeContext;
use AIArmada\FilamentAuthz\Support\OwnerContextTeamResolver;

describe('OwnerContextTeamResolver', function (): void {
    beforeEach(function (): void {
        AuthzScopeContext::clear();
    });

    it('keeps team id resolution working without active request lifecycle', function (): void {
        $resolver = new OwnerContextTeamResolver;
        $previousRequest = app()->bound('request') ? app('request') : null;

        app()->instance('request', new class
        {
            public function setUserResolver(callable $resolver): void {}
        });

        try {
            config()->set('commerce-support.owner.team_type', null);

            $resolver->setPermissionsTeamId('team-123');

            expect($resolver->getPermissionsTeamId())->toBe('team-123');
        } finally {
            if ($previousRequest !== null) {
                app()->instance('request', $previousRequest);
            }

            AuthzScopeContext::clear();
        }
    });
});
