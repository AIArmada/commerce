<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Enums\ConditionOperator;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use AIArmada\FilamentAuthz\Models\AccessPolicy;
use AIArmada\FilamentAuthz\Services\PolicyBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Ensure necessary tables exist
});

describe('PolicyBuilder', function (): void {
    describe('create', function (): void {
        it('creates a builder with name', function (): void {
            $builder = PolicyBuilder::create('test-policy');
            $array = $builder->toArray();

            expect($array['name'])->toBe('test-policy')
                ->and($array['slug'])->toBe('test-policy');
        });
    });

    describe('description', function (): void {
        it('sets description', function (): void {
            $array = PolicyBuilder::create('test')
                ->description('A test policy')
                ->toArray();

            expect($array['description'])->toBe('A test policy');
        });
    });

    describe('effect methods', function (): void {
        it('defaults to allow effect', function (): void {
            $array = PolicyBuilder::create('test')->toArray();

            expect($array['effect'])->toBe('allow');
        });

        it('sets allow effect', function (): void {
            $array = PolicyBuilder::create('test')
                ->allow()
                ->toArray();

            expect($array['effect'])->toBe('allow');
        });

        it('sets deny effect', function (): void {
            $array = PolicyBuilder::create('test')
                ->deny()
                ->toArray();

            expect($array['effect'])->toBe('deny');
        });
    });

    describe('target methods', function (): void {
        it('sets action', function (): void {
            $array = PolicyBuilder::create('test')
                ->action('view')
                ->toArray();

            expect($array['target_action'])->toBe('view');
        });

        it('sets resource', function (): void {
            $array = PolicyBuilder::create('test')
                ->resource('posts')
                ->toArray();

            expect($array['target_resource'])->toBe('posts');
        });

        it('sets all actions on resource', function (): void {
            $array = PolicyBuilder::create('test')
                ->allActionsOn('users')
                ->toArray();

            expect($array['target_action'])->toBe('*')
                ->and($array['target_resource'])->toBe('users');
        });

        it('sets any resource for action', function (): void {
            $array = PolicyBuilder::create('test')
                ->anyResource('delete')
                ->toArray();

            expect($array['target_action'])->toBe('delete')
                ->and($array['target_resource'])->toBe('*');
        });
    });

    describe('condition methods', function (): void {
        it('adds condition with when', function (): void {
            $array = PolicyBuilder::create('test')
                ->when('user.id', ConditionOperator::Equals, 1)
                ->toArray();

            expect($array['conditions'])->toHaveCount(1)
                ->and($array['conditions'][0]['attribute'])->toBe('user.id')
                ->and($array['conditions'][0]['operator'])->toBe('eq')
                ->and($array['conditions'][0]['value'])->toBe(1);
        });

        it('adds whereEquals condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereEquals('status', 'active')
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('eq');
        });

        it('adds whereNotEquals condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereNotEquals('status', 'banned')
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('neq');
        });

        it('adds whereGreaterThan condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereGreaterThan('age', 18)
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('gt');
        });

        it('adds whereLessThan condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereLessThan('score', 100)
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('lt');
        });

        it('adds whereIn condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereIn('role', ['admin', 'editor'])
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('in')
                ->and($array['conditions'][0]['value'])->toBe(['admin', 'editor']);
        });

        it('adds whereNotIn condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereNotIn('status', ['banned', 'suspended'])
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('not_in');
        });

        it('adds whereContains condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereContains('tags', 'featured')
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('contains');
        });

        it('adds whereStartsWith condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereStartsWith('email', 'admin')
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('starts_with');
        });

        it('adds whereBetween condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereBetween('age', 18, 65)
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('between')
                ->and($array['conditions'][0]['value'])->toBe([18, 65]);
        });

        it('adds whereNull condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereNull('deleted_at')
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('is_null');
        });

        it('adds whereNotNull condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereNotNull('email_verified_at')
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('is_not_null');
        });

        it('adds whereMatches condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereMatches('email', '/^admin@.*/')
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('matches');
        });
    });

    describe('convenience condition methods', function (): void {
        it('adds whereOwner condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereOwner()
                ->toArray();

            expect($array['conditions'][0]['attribute'])->toBe('resource.user_id')
                ->and($array['conditions'][0]['value'])->toBe('@user.id');
        });

        it('adds whereTeamMember condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereTeamMember()
                ->toArray();

            expect($array['conditions'][0]['attribute'])->toBe('user.team_ids')
                ->and($array['conditions'][0]['operator'])->toBe('contains');
        });

        it('adds whereHasRole condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereHasRole('admin')
                ->toArray();

            expect($array['conditions'][0]['attribute'])->toBe('user.roles')
                ->and($array['conditions'][0]['value'])->toBe('admin');
        });

        it('adds whereIpInRange condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->whereIpInRange(['192.168.1.0/24', '10.0.0.0/8'])
                ->toArray();

            expect($array['conditions'][0]['operator'])->toBe('in');
        });

        it('adds duringBusinessHours condition', function (): void {
            $array = PolicyBuilder::create('test')
                ->duringBusinessHours()
                ->toArray();

            expect($array['conditions'][0]['attribute'])->toBe('request.hour')
                ->and($array['conditions'][0]['operator'])->toBe('between')
                ->and($array['conditions'][0]['value'])->toBe([9, 17]);
        });
    });

    describe('priority methods', function (): void {
        it('sets priority', function (): void {
            $array = PolicyBuilder::create('test')
                ->priority(50)
                ->toArray();

            expect($array['priority'])->toBe(50);
        });

        it('sets high priority', function (): void {
            $array = PolicyBuilder::create('test')
                ->highPriority()
                ->toArray();

            expect($array['priority'])->toBe(100);
        });

        it('sets low priority', function (): void {
            $array = PolicyBuilder::create('test')
                ->lowPriority()
                ->toArray();

            expect($array['priority'])->toBe(-100);
        });
    });

    describe('status and validity', function (): void {
        it('defaults to active', function (): void {
            $array = PolicyBuilder::create('test')->toArray();

            expect($array['is_active'])->toBeTrue();
        });

        it('sets inactive', function (): void {
            $array = PolicyBuilder::create('test')
                ->inactive()
                ->toArray();

            expect($array['is_active'])->toBeFalse();
        });

        it('sets valid between dates', function (): void {
            $from = Carbon::parse('2024-01-01');
            $until = Carbon::parse('2024-12-31');

            $array = PolicyBuilder::create('test')
                ->validBetween($from, $until)
                ->toArray();

            expect($array['valid_from'])->toBe('2024-01-01 00:00:00')
                ->and($array['valid_until'])->toBe('2024-12-31 00:00:00');
        });

        it('sets valid from date', function (): void {
            $from = Carbon::parse('2024-06-01');

            $array = PolicyBuilder::create('test')
                ->validFrom($from)
                ->toArray();

            expect($array['valid_from'])->toBe('2024-06-01 00:00:00')
                ->and($array['valid_until'])->toBeNull();
        });

        it('sets valid until date', function (): void {
            $until = Carbon::parse('2024-12-31');

            $array = PolicyBuilder::create('test')
                ->validUntil($until)
                ->toArray();

            expect($array['valid_from'])->toBeNull()
                ->and($array['valid_until'])->toBe('2024-12-31 00:00:00');
        });
    });

    describe('metadata', function (): void {
        it('sets metadata', function (): void {
            $metadata = ['created_by' => 'admin', 'version' => 1];

            $array = PolicyBuilder::create('test')
                ->metadata($metadata)
                ->toArray();

            expect($array['metadata'])->toBe($metadata);
        });
    });

    describe('save', function (): void {
        it('saves policy to database', function (): void {
            $policy = PolicyBuilder::create('database-test-policy')
                ->description('Test description')
                ->allow()
                ->resource('posts')
                ->action('view')
                ->whereEquals('user.active', true)
                ->priority(10)
                ->save();

            expect($policy)->toBeInstanceOf(AccessPolicy::class)
                ->and($policy->exists)->toBeTrue()
                ->and($policy->name)->toBe('database-test-policy')
                ->and($policy->slug)->toBe('database-test-policy')
                ->and($policy->effect)->toBe(PolicyEffect::Allow)
                ->and($policy->target_resource)->toBe('posts');
        });
    });

    describe('toArray', function (): void {
        it('returns complete array representation', function (): void {
            $array = PolicyBuilder::create('complete-policy')
                ->description('Full policy')
                ->deny()
                ->resource('orders')
                ->action('delete')
                ->whereEquals('status', 'completed')
                ->priority(50)
                ->metadata(['note' => 'test'])
                ->toArray();

            expect($array)->toHaveKeys([
                'name',
                'slug',
                'description',
                'effect',
                'target_action',
                'target_resource',
                'conditions',
                'priority',
                'is_active',
                'valid_from',
                'valid_until',
                'metadata',
            ]);
        });
    });

    describe('chaining', function (): void {
        it('supports fluent chaining', function (): void {
            $array = PolicyBuilder::create('chained-policy')
                ->description('Full featured policy')
                ->deny()
                ->resource('users')
                ->action('delete')
                ->whereNotEquals('role', 'admin')
                ->whereGreaterThan('age', 0)
                ->highPriority()
                ->metadata(['audit' => true])
                ->toArray();

            expect($array['name'])->toBe('chained-policy')
                ->and($array['effect'])->toBe('deny')
                ->and($array['conditions'])->toHaveCount(2)
                ->and($array['priority'])->toBe(100);
        });
    });
});
