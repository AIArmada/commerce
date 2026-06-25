<?php

declare(strict_types=1);

use AIArmada\Communications\Console\Commands\DispatchDueCommunicationsCommand;
use AIArmada\Communications\Console\Commands\ExpireCommunicationsCommand;
use AIArmada\Communications\Console\Commands\PruneCommunicationDataCommand;
use AIArmada\Communications\Console\Commands\ReconcileCommunicationStatusCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

final class CommunicationsCommandTestOwner extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function getTable(): string
    {
        return 'communications_command_test_owners';
    }
}

function owner(): CommunicationsCommandTestOwner
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

test('ReconcileCommunicationStatusCommand exists and can be instantiated', function (): void {
    $command = app(ReconcileCommunicationStatusCommand::class);

    expect($command)->toBeInstanceOf(ReconcileCommunicationStatusCommand::class);
});

test('ReconcileCommunicationStatusCommand dry-run returns success with no communications', function (): void {
    $exitCode = Artisan::call('communications:reconcile', ['--dry-run' => true]);

    expect($exitCode)->toBe(ReconcileCommunicationStatusCommand::SUCCESS);
    expect(Artisan::output())->toContain('No communications to reconcile');
});

test('ReconcileCommunicationStatusCommand accepts owner flag', function (): void {
    $owner = owner();

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

test('DispatchDueCommunicationsCommand dry-run returns success', function (): void {
    $exitCode = Artisan::call('communications:dispatch-due', ['--dry-run' => true]);

    expect($exitCode)->toBe(DispatchDueCommunicationsCommand::SUCCESS);
    expect(Artisan::output())->toContain('No due communications found');
});

test('DispatchDueCommunicationsCommand accepts owner flag', function (): void {
    $owner = owner();

    $exitCode = Artisan::call('communications:dispatch-due', [
        '--dry-run' => true,
        '--owner' => $owner->getMorphClass() . ':' . $owner->getKey(),
    ]);

    expect($exitCode)->toBe(DispatchDueCommunicationsCommand::SUCCESS);
});

test('ExpireCommunicationsCommand dry-run returns success', function (): void {
    $exitCode = Artisan::call('communications:expire', ['--dry-run' => true]);

    expect($exitCode)->toBe(ExpireCommunicationsCommand::SUCCESS);
    expect(Artisan::output())->toContain('No expired communications found');
});

test('ExpireCommunicationsCommand accepts owner flag', function (): void {
    $owner = owner();

    $exitCode = Artisan::call('communications:expire', [
        '--dry-run' => true,
        '--owner' => $owner->getMorphClass() . ':' . $owner->getKey(),
    ]);

    expect($exitCode)->toBe(ExpireCommunicationsCommand::SUCCESS);
});

test('PruneCommunicationDataCommand exists', function (): void {
    $command = app(PruneCommunicationDataCommand::class);

    expect($command)->toBeInstanceOf(PruneCommunicationDataCommand::class);
});

test('PruneCommunicationDataCommand dry-run returns success', function (): void {
    $exitCode = Artisan::call('communications:prune', ['--dry-run' => true]);

    expect($exitCode)->toBe(PruneCommunicationDataCommand::SUCCESS);
});

test('PruneCommunicationDataCommand accepts owner flag', function (): void {
    $owner = owner();

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
