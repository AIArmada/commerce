<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventApprovalRequest;
use AIArmada\Events\Models\EventSubmission;
use Illuminate\Database\Eloquent\Builder;

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
            'approvable_type' => EventSubmission::class,
            'approvable_id' => $submission->id,
        ]);

        EventApprovalRequest::factory()->create([
            'approvable_type' => Event::class,
            'approvable_id' => $event->id,
        ]);

        $records = EventApprovalRequest::query()
            ->whereHasMorph(
                'approvable',
                [EventSubmission::class],
                fn (Builder $query): Builder => $query->whereHas(
                    'event',
                    fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false),
                ),
            )
            ->get();

        expect($records)->toHaveCount(1);
    });
});
