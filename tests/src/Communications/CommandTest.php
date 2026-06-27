<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Communications\Console\Commands\DispatchDueCommunicationsCommand;
use AIArmada\Communications\Console\Commands\ExpireCommunicationsCommand;
use AIArmada\Communications\Console\Commands\PruneCommunicationDataCommand;
use AIArmada\Communications\Console\Commands\ReconcileCommunicationStatusCommand;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\NotificationInbox;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('dispatch-due command runs without error', function (): void {
    $exitCode = Artisan::call('communications:dispatch-due');
    expect($exitCode)->toBe(0);
});

test('prune command runs without error', function (): void {
    $exitCode = Artisan::call('communications:prune');
    expect($exitCode)->toBe(0);
});

test('prune-inboxes command runs without error', function (): void {
    $exitCode = Artisan::call('communications:prune-inboxes');
    expect($exitCode)->toBe(0);
});

test('expire command runs without error', function (): void {
    Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'expire-test',
        'status' => CommunicationStatus::Scheduled,
        'expires_at' => now()->subDay(),
    ]);

    $exitCode = Artisan::call('communications:expire');
    expect($exitCode)->toBe(0);
});

test('reconcile command runs without error', function (): void {
    $exitCode = Artisan::call('communications:reconcile');
    expect($exitCode)->toBe(0);
});

test('reconcile command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:reconcile', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('dispatch-due command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:dispatch-due', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('expire command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:expire', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('prune command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:prune', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('prune-inboxes command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:prune-inboxes', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('prune-inboxes command reports archived inbox entries in dry-run mode', function (): void {
    $user = User::create([
        'name' => 'Inbox User',
        'email' => 'inbox-user-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $communication = Communication::create([
        'direction' => CommunicationDirection::Internal,
        'category' => CommunicationCategory::Internal,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'prune-inboxes-test',
        'status' => CommunicationStatus::Completed,
    ]);

    NotificationInbox::create([
        'recipient_type' => $user::class,
        'recipient_id' => $user->id,
        'communication_id' => $communication->id,
        'family' => NotificationFamily::SystemAnnouncement,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::SystemAlert,
        'title' => 'Archived Inbox',
        'archived_at' => now()->subDays(100),
    ]);

    $exitCode = Artisan::call('communications:prune-inboxes', ['--dry-run' => true]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Would prune 1 inbox entries.');
});

test('replay-webhooks command runs without error when no events exist', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks');
    expect($exitCode)->toBe(0);
});

test('replay-webhooks command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('replay-webhooks command accepts force flag', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks', ['--force' => true]);
    expect($exitCode)->toBe(0);
});

test('replay-webhooks command accepts provider filter', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks', ['--provider' => 'sendgrid']);
    expect($exitCode)->toBe(0);
});

test('replay-webhooks command accepts communication filter', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks', ['--communication' => 'test-id']);
    expect($exitCode)->toBe(0);
});

final class CommunicationsCommandTestOwner extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function getTable(): string
    {
        return 'communications_command_test_owners';
    }
}

function commandTestOwner(): CommunicationsCommandTestOwner
{
    return CommunicationsCommandTestOwner::query()->create(['name' => 'Command Owner']);
}

beforeEach(function (): void {
    Schema::create('communications_command_test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

test('ReconcileCommunicationStatusCommand can be instantiated', function (): void {
    $command = app(ReconcileCommunicationStatusCommand::class);
    expect($command)->toBeInstanceOf(ReconcileCommunicationStatusCommand::class);
});

test('ReconcileCommunicationStatusCommand dry-run shows no communications message', function (): void {
    $exitCode = Artisan::call('communications:reconcile', ['--dry-run' => true]);
    expect($exitCode)->toBe(ReconcileCommunicationStatusCommand::SUCCESS);
    expect(Artisan::output())->toContain('No communications to reconcile');
});

test('ReconcileCommunicationStatusCommand accepts owner flag', function (): void {
    $owner = commandTestOwner();
    $exitCode = Artisan::call('communications:reconcile', [
        '--dry-run' => true,
        '--owner' => $owner->getMorphClass() . ':' . $owner->getKey(),
    ]);
    expect($exitCode)->toBe(ReconcileCommunicationStatusCommand::SUCCESS);
});

test('ReconcileCommunicationStatusCommand invalid owner format shows error', function (): void {
    $exitCode = Artisan::call('communications:reconcile', [
        '--dry-run' => true,
        '--owner' => 'invalid-format',
    ]);
    expect($exitCode)->toBe(ReconcileCommunicationStatusCommand::FAILURE);
    expect(Artisan::output())->toContain('Invalid --owner format');
});

test('DispatchDueCommunicationsCommand dry-run shows no due message', function (): void {
    $exitCode = Artisan::call('communications:dispatch-due', ['--dry-run' => true]);
    expect($exitCode)->toBe(DispatchDueCommunicationsCommand::SUCCESS);
    expect(Artisan::output())->toContain('No due communications found');
});

test('DispatchDueCommunicationsCommand accepts owner flag', function (): void {
    $owner = commandTestOwner();
    $exitCode = Artisan::call('communications:dispatch-due', [
        '--dry-run' => true,
        '--owner' => $owner->getMorphClass() . ':' . $owner->getKey(),
    ]);
    expect($exitCode)->toBe(DispatchDueCommunicationsCommand::SUCCESS);
});

test('ExpireCommunicationsCommand dry-run shows no expired message', function (): void {
    $exitCode = Artisan::call('communications:expire', ['--dry-run' => true]);
    expect($exitCode)->toBe(ExpireCommunicationsCommand::SUCCESS);
    expect(Artisan::output())->toContain('No expired communications found');
});

test('ExpireCommunicationsCommand accepts owner flag', function (): void {
    $owner = commandTestOwner();
    $exitCode = Artisan::call('communications:expire', [
        '--dry-run' => true,
        '--owner' => $owner->getMorphClass() . ':' . $owner->getKey(),
    ]);
    expect($exitCode)->toBe(ExpireCommunicationsCommand::SUCCESS);
});

test('PruneCommunicationDataCommand can be instantiated', function (): void {
    $command = app(PruneCommunicationDataCommand::class);
    expect($command)->toBeInstanceOf(PruneCommunicationDataCommand::class);
});

test('PruneCommunicationDataCommand dry-run returns success', function (): void {
    $exitCode = Artisan::call('communications:prune', ['--dry-run' => true]);
    expect($exitCode)->toBe(PruneCommunicationDataCommand::SUCCESS);
});

test('PruneCommunicationDataCommand accepts owner flag', function (): void {
    $owner = commandTestOwner();
    $exitCode = Artisan::call('communications:prune', [
        '--dry-run' => true,
        '--owner' => $owner->getMorphClass() . ':' . $owner->getKey(),
    ]);
    expect($exitCode)->toBe(PruneCommunicationDataCommand::SUCCESS);
});

test('all communication commands are registered', function (): void {
    $kernel = app(Kernel::class);
    $commands = $kernel->all();
    expect($commands)->toHaveKey('communications:reconcile');
    expect($commands)->toHaveKey('communications:dispatch-due');
    expect($commands)->toHaveKey('communications:expire');
    expect($commands)->toHaveKey('communications:prune');
});
