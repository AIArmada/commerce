<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContextTeamResolver;
use AIArmada\FilamentAuthz\Support\AuthzScopeContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

describe('OwnerContextTeamResolver', function (): void {
    beforeEach(function (): void {
        AuthzScopeContext::clear();
    });

    it('delegates team id resolution to OwnerContext', function (): void {
        $resolver = new OwnerContextTeamResolver;

        $owner = new class extends Model
        {
            protected $keyType = 'string';

            public $incrementing = false;
        };
        $owner->setAttribute($owner->getKeyName(), 'team-123');

        OwnerContext::withOwner($owner, function () use ($resolver): void {
            expect($resolver->getPermissionsTeamId())->toBe('team-123');
        });
    });

    it('resolves team id from config when receiving scalar', function (): void {
        $resolver = new OwnerContextTeamResolver;
        $previousRequest = app()->bound('request') ? app('request') : null;

        $request = new class extends Request
        {
            public function setUserResolver(callable $resolver): void {}
        };
        $request->setMethod('GET');
        $request->server->set('REQUEST_URI', '/');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.1');

        app()->instance('request', $request);

        try {
            config()->set('commerce-support.owner.team_type', User::class);

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
