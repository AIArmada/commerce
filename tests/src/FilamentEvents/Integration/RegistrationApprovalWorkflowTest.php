<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Events\RegistrationApproved;
use AIArmada\Events\Events\RegistrationRejected;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\FilamentEvents\Resources\RegistrationResource;
use AIArmada\FilamentEvents\Resources\RegistrationResource\Pages\CreateRegistration;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Event;

afterEach(function (): void {
    Mockery::close();
});

function makeRegistrationApprovalTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

it('wires registration approval actions and keeps create forms pending for approval-required occurrences', function (): void {
    Event::fake([RegistrationApproved::class, RegistrationRejected::class]);

    $user = User::query()->create([
        'name' => 'Filament Reviewer',
        'email' => 'filament-reviewer@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user);

    $event = EventModel::create([
        'name' => 'Filament Approval Event',
        'slug' => 'filament-approval-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'approval_required' => true,
        'starts_at' => now()->addDays(4),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $service = app(RegistrationService::class);

    $approvalTarget = $service->createForOccurrence($occurrence, [
        'name' => 'Approval Target',
        'email' => 'approval-target@example.com',
    ]);

    $rejectionTarget = $service->createForOccurrence($occurrence, [
        'name' => 'Rejection Target',
        'email' => 'rejection-target@example.com',
    ]);

    $waitlistedTarget = Registration::create([
        'occurrence_id' => $occurrence->id,
        'status' => RegistrationStatus::Waitlisted,
        'first_name' => 'Waitlisted',
        'last_name' => 'Guest',
        'email' => 'waitlisted-filament@example.com',
    ]);

    expect($approvalTarget->status)->toBe(RegistrationStatus::Pending)
        ->and($rejectionTarget->status)->toBe(RegistrationStatus::Pending)
        ->and(RegistrationResource::approveAction()->record($waitlistedTarget)->isVisible())->toBeTrue()
        ->and(RegistrationResource::approveAction()->record($rejectionTarget)->isVisible())->toBeTrue();

    $table = RegistrationResource::table(makeRegistrationApprovalTable());

    $approve = $table->getAction('approve');
    $reject = $table->getAction('reject');

    expect($approve)->not->toBeNull()
        ->and($reject)->not->toBeNull();

    $approve?->call(['record' => $approvalTarget]);
    $rejectionReason = 'Applicant did not meet eligibility requirements';
    $reject?->call([
        'record' => $rejectionTarget,
        'data' => ['reason' => $rejectionReason],
    ]);

    $approvalTarget->refresh();
    $rejectionTarget->refresh();

    expect($approvalTarget->status)->toBe(RegistrationStatus::Confirmed)
        ->and(data_get($approvalTarget->metadata, 'approval_transition'))->toBe('approve')
        ->and(data_get($approvalTarget->metadata, 'approval_actor_id'))->toBe((string) $user->getKey())
        ->and($rejectionTarget->status)->toBe(RegistrationStatus::Cancelled)
        ->and(data_get($rejectionTarget->metadata, 'approval_transition'))->toBe('reject')
        ->and(data_get($rejectionTarget->metadata, 'approval_rejection_reason'))->toBe($rejectionReason)
        ->and(data_get($rejectionTarget->metadata, 'cancellation_reason'))->toBe($rejectionReason);

    Event::assertDispatched(RegistrationApproved::class);
    Event::assertDispatched(RegistrationRejected::class);

    $createPage = new CreateRegistration;
    $method = new ReflectionMethod($createPage::class, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    $mutated = $method->invoke($createPage, [
        'occurrence_id' => $occurrence->id,
        'status' => RegistrationStatus::Confirmed->value,
        'first_name' => 'Pending',
        'last_name' => 'Applicant',
        'email' => 'pending-applicant@example.com',
    ]);

    expect($mutated['status'])->toBe(RegistrationStatus::Pending->value);
});
