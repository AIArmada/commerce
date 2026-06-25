<?php

declare(strict_types=1);

use AIArmada\Authz\Support\AuthzScopeContext;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContextTeamResolver;
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

        $request = Request::create('/');

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
