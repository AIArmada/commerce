<?php

declare(strict_types=1);

use AIArmada\Jnt\Models\JntWebhookLog;
use Illuminate\Support\Facades\DB;

it('limits the JNT webhook model to JNT rows in the shared webhook table', function (): void {
    config(['jnt.owner.enabled' => false]);

    JntWebhookLog::query()->create([
        'name' => 'other.webhooks.status',
        'url' => '/webhooks/other/status',
        'payload' => ['provider' => 'other'],
        'processing_status' => JntWebhookLog::STATUS_PENDING,
    ]);

    JntWebhookLog::query()->create([
        'name' => JntWebhookLog::WEBHOOK_NAME,
        'url' => '/webhooks/jnt/status',
        'payload' => ['provider' => 'jnt'],
        'processing_status' => JntWebhookLog::STATUS_PENDING,
    ]);

    expect(JntWebhookLog::query()->count())->toBe(1)
        ->and(JntWebhookLog::query()->firstOrFail()->name)->toBe(JntWebhookLog::WEBHOOK_NAME)
        ->and(DB::table('webhook_calls')->where('name', 'other.webhooks.status')->count())->toBe(1);
});
