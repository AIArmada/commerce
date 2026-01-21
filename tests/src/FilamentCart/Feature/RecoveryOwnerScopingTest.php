<?php

declare(strict_types=1);

use AIArmada\Cart\Models\RecoveryCampaign;
use AIArmada\Cart\Models\RecoveryTemplate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentCart\Resources\RecoveryCampaignResource;
use AIArmada\FilamentCart\Resources\RecoveryTemplateResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scopes recovery resources by owner and blocks cross-tenant template references', function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
    config()->set('cart.owner.enabled', true);
    config()->set('filament-cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
    config()->set('filament-cart.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $templateA = RecoveryTemplate::query()->create([
        'name' => 'Template A',
        'type' => 'email',
        'status' => 'active',
        'is_default' => false,
    ]);

    $campaignA = RecoveryCampaign::query()->create([
        'name' => 'Campaign A',
        'status' => 'active',
        'trigger_type' => 'abandoned',
        'control_template_id' => $templateA->id,
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerB));

    $templateB = RecoveryTemplate::query()->create([
        'name' => 'Template B',
        'type' => 'email',
        'status' => 'active',
        'is_default' => false,
    ]);

    RecoveryCampaign::query()->create([
        'name' => 'Campaign B',
        'status' => 'active',
        'trigger_type' => 'abandoned',
        'control_template_id' => $templateB->id,
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    expect(RecoveryTemplateResource::getEloquentQuery()->count())->toBe(1);
    expect(RecoveryTemplateResource::getEloquentQuery()->first()?->id)->toBe($templateA->id);

    expect(RecoveryCampaignResource::getEloquentQuery()->count())->toBe(1);
    expect(RecoveryCampaignResource::getEloquentQuery()->first()?->id)->toBe($campaignA->id);

    expect(fn () => RecoveryCampaign::query()->create([
        'name' => 'Campaign Cross Tenant',
        'status' => 'active',
        'trigger_type' => 'abandoned',
        'control_template_id' => $templateB->id,
    ]))->toThrow(RuntimeException::class);
});
