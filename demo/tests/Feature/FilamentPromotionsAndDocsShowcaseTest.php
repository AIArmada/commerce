<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentPromotions\Resources\PromotionResource;
use App\Models\User;
use Database\Seeders\DocsShowcaseSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PromotionsShowcaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('promotions and docs are surfaced on the demo admin dashboard', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $owner = User::query()
        ->where('email', 'admin@commerce.demo')
        ->firstOrFail();

    OwnerContext::withOwner($owner, function (): void {
        $this->seed(PromotionsShowcaseSeeder::class);
        $this->seed(DocsShowcaseSeeder::class);
    });

    $this->actingAs($owner)
        ->withSession(['demo_owner_id' => $owner->id])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Commerce Command Center');

    $this->actingAs($owner)
        ->withSession(['demo_owner_id' => $owner->id])
        ->get(PromotionResource::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('Checkout Confidence Booster')
        ->assertSee('Welcome Back Offer');

    $this->actingAs($owner)
        ->withSession(['demo_owner_id' => $owner->id])
        ->get(DocResource::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('INV-DEMO-000101')
        ->assertSee('QUO-DEMO-000201');
});
