<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Sent;
use AIArmada\FilamentDocs\Support\DocBulkActions;

uses(TestCase::class);

it('marks only eligible documents as sent and reports skips (M6)', function (): void {
    $draft = Doc::factory()->create(['status' => Draft::class]);
    $sent = Doc::factory()->create(['status' => Sent::class]);

    $result = DocBulkActions::markSelectedAsSent(Doc::query()->whereKey([$draft->id, $sent->id])->get());

    expect($result)->toBe(['marked' => 1, 'skipped' => 1]);
    expect($draft->fresh()->status->equals(Sent::class))->toBeTrue();
    expect($sent->fresh()->status->equals(Sent::class))->toBeTrue();

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toContain('marked as sent');
});
