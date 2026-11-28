<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Governance\Models\EventApprovalRequest;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use AIArmada\FilamentEvents\Pages\ApprovalQueue;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('only lists approval requests for event submissions', function (): void {
    $owner = User::query()->create([
        'name' => 'Approval Queue Owner',
        'email' => 'approval-queue-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($owner, function (): void {
        $event = Event::factory()->create();
        $submission = EventSubmission::factory()->create([
            'event_id' => $event->id,
        ]);

        EventApprovalRequest::factory()->create([
            'approvable_type' => $submission->getMorphClass(),
            'approvable_id' => $submission->id,
        ]);

        EventApprovalRequest::factory()->create([
            'approvable_type' => Event::class,
            'approvable_id' => $event->id,
        ]);

        $records = (new ApprovalQueue)->table(Table::make(Mockery::mock(HasTable::class)))->getQuery()->get();

        expect($records)->toHaveCount(1)
            ->and($records->first()?->approvable_id)->toBe($submission->id)
            ->and($records->first()?->approvable?->relationLoaded('event'))->toBeTrue();
    });
});
