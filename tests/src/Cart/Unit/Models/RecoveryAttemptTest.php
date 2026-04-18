<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartModel;
use AIArmada\Cart\Models\RecoveryAttempt;
use AIArmada\Cart\Models\RecoveryCampaign;
use AIArmada\Cart\Models\RecoveryTemplate;
use AIArmada\Cart\States\Clicked;
use AIArmada\Cart\States\Converted;
use AIArmada\Cart\States\Opened;
use AIArmada\Cart\States\Scheduled;
use AIArmada\Cart\States\Sent;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('cart.owner.enabled', false);
    OwnerContext::clearOverride();

    $table = config('cart.database.table_prefix', 'cart_') . 'recovery_attempts';

    if (! Schema::hasTable($table)) {
        Schema::create($table, function ($table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id');
            $table->foreignUuid('cart_id');
            $table->foreignUuid('template_id')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('channel')->nullable();
            $table->string('status')->nullable();
            $table->integer('attempt_number')->default(1);
            $table->boolean('is_control')->default(false);
            $table->boolean('is_variant')->default(false);
            $table->string('discount_code')->nullable();
            $table->integer('discount_value_cents')->nullable();
            $table->boolean('free_shipping_offered')->default(false);
            $table->timestamp('offer_expires_at')->nullable();
            $table->integer('cart_value_cents')->default(0);
            $table->integer('cart_items_count')->default(0);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('message_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('failure_reason')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();
        });
    }
});

it('casts recovery attempt status to states', function (): void {
    $attempt = RecoveryAttempt::create([
        'campaign_id' => 'campaign-1',
        'cart_id' => 'cart-1',
        'channel' => 'email',
        'status' => Scheduled::class,
        'attempt_number' => 1,
    ]);

    expect($attempt->status)->toBeInstanceOf(Scheduled::class);
    expect($attempt->isScheduled())->toBeTrue();
});

it('updates recovery attempt status helpers', function (): void {
    $attempt = RecoveryAttempt::create([
        'campaign_id' => 'campaign-2',
        'cart_id' => 'cart-2',
        'channel' => 'email',
        'status' => Scheduled::class,
        'attempt_number' => 1,
    ]);

    $attempt->markAsSent('message-1');
    $attempt->refresh();
    expect($attempt->status)->toBeInstanceOf(Sent::class);
    expect($attempt->isSent())->toBeTrue();

    $attempt->markAsOpened();
    $attempt->refresh();
    expect($attempt->status)->toBeInstanceOf(Opened::class);
    expect($attempt->isOpened())->toBeTrue();

    $attempt->markAsClicked();
    $attempt->refresh();
    expect($attempt->status)->toBeInstanceOf(Clicked::class);
    expect($attempt->isClicked())->toBeTrue();

    $attempt->markAsConverted();
    $attempt->refresh();
    expect($attempt->status)->toBeInstanceOf(Converted::class);
    expect($attempt->isConverted())->toBeTrue();
});

it('rejects carts from another owner when owner scoping is enabled', function (): void {
    config()->set('cart.owner.enabled', true);

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

    $cart = OwnerContext::withOwner($ownerA, static function (): CartModel {
        return CartModel::create([
            'identifier' => 'cart-owner-a',
            'instance' => 'default',
            'items' => [],
            'conditions' => [],
            'metadata' => [],
            'version' => 1,
        ]);
    });

    $campaign = OwnerContext::withOwner($ownerB, static function (): RecoveryCampaign {
        return RecoveryCampaign::create([
            'name' => 'Campaign B',
            'status' => 'active',
            'trigger_type' => 'abandoned',
        ]);
    });

    $template = OwnerContext::withOwner($ownerB, static function (): RecoveryTemplate {
        return RecoveryTemplate::create([
            'name' => 'Template B',
            'type' => 'email',
            'status' => 'draft',
        ]);
    });

    expect(static function () use ($campaign, $cart, $template, $ownerB): void {
        OwnerContext::withOwner($ownerB, static function () use ($campaign, $cart, $template): void {
            RecoveryAttempt::create([
                'campaign_id' => $campaign->id,
                'cart_id' => $cart->id,
                'template_id' => $template->id,
                'channel' => 'email',
                'status' => Scheduled::class,
                'attempt_number' => 1,
            ]);
        });
    })->toThrow(RuntimeException::class, 'Invalid cart_id');
});

it('rejects empty cart identifiers when owner scoping is enabled', function (): void {
    config()->set('cart.owner.enabled', true);

    $owner = User::query()->create([
        'name' => 'Owner Empty Cart',
        'email' => 'owner-empty-cart@example.com',
        'password' => 'secret',
    ]);

    $campaign = OwnerContext::withOwner($owner, static function (): RecoveryCampaign {
        return RecoveryCampaign::create([
            'name' => 'Campaign Empty Cart',
            'status' => 'active',
            'trigger_type' => 'abandoned',
        ]);
    });

    $template = OwnerContext::withOwner($owner, static function (): RecoveryTemplate {
        return RecoveryTemplate::create([
            'name' => 'Template Empty Cart',
            'type' => 'email',
            'status' => 'draft',
        ]);
    });

    expect(static function () use ($campaign, $template, $owner): void {
        OwnerContext::withOwner($owner, static function () use ($campaign, $template): void {
            RecoveryAttempt::create([
                'campaign_id' => $campaign->id,
                'cart_id' => '',
                'template_id' => $template->id,
                'channel' => 'email',
                'status' => Scheduled::class,
                'attempt_number' => 1,
            ]);
        });
    })->toThrow(RuntimeException::class, 'Invalid cart_id');
});
