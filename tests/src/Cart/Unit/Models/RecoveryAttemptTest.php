<?php

declare(strict_types=1);

use AIArmada\Cart\Models\RecoveryAttempt;
use AIArmada\Cart\States\Clicked;
use AIArmada\Cart\States\Converted;
use AIArmada\Cart\States\Opened;
use AIArmada\Cart\States\Scheduled;
use AIArmada\Cart\States\Sent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $table = config('cart.database.table_prefix', 'cart_') . 'recovery_attempts';

    if (! Schema::hasTable($table)) {
        Schema::create($table, function ($table): void {
            $table->uuid('id')->primary();
            $table->string('campaign_id')->nullable();
            $table->string('cart_id')->nullable();
            $table->string('template_id')->nullable();
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
