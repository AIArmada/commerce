<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Features\Aggregate\Actions\ResolveCommunicationThreadAction;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationThread;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

function communicationsEngineOwner(string $suffix): User
{
    return User::query()->create([
        'name' => 'Communications Engine Owner ' . $suffix,
        'email' => 'communications-engine-' . $suffix . '-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function communicationsEngineWaitForFile(string $path, float $timeoutSeconds = 15.0): void
{
    $deadline = microtime(true) + $timeoutSeconds;

    while (! is_file($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for engine race file [' . $path . '].');
        }

        usleep(1000);
    }
}

function communicationsEngineWaitForTransactionLock(float $timeoutSeconds = 15.0): bool
{
    $deadline = microtime(true) + $timeoutSeconds;
    $applicationName = DB::connection()->getConfig('application_name');

    do {
        $blocked = DB::selectOne(<<<'SQL'
            select count(*) as blocked
            from pg_stat_activity
            where pid <> pg_backend_pid()
              and application_name = ?
              and wait_event_type = 'Lock'
              and wait_event = 'transactionid'
        SQL, [$applicationName]);

        if ((int) ($blocked->blocked ?? 0) > 0) {
            return true;
        }

        usleep(1000);
    } while (microtime(true) < $deadline);

    return false;
}

/**
 * @return array{results: array<string, mixed>, channel: string, external_thread_id: string, owner: User|null}
 */
function communicationsEngineRunContention(string $mode): array
{
    $owner = $mode === 'owner' ? communicationsEngineOwner('owner') : null;
    $channel = 'engine';
    $externalThreadId = 'thread-engine-' . Str::uuid();

    $results = EngineRace::run([
        'winner' => function (string $workdir) use ($channel, $externalThreadId, $owner): array {
            DB::connection()->getPdo();
            communicationsEngineWaitForFile($workdir . '/loser-connected');

            DB::beginTransaction();

            try {
                OwnerContext::withOwner($owner, function () use ($channel, $externalThreadId, $owner): void {
                    $thread = new CommunicationThread;
                    $thread->channel = $channel;
                    $thread->external_thread_id = $externalThreadId;
                    $thread->status = 'open';
                    $thread->opened_at = now();
                    $thread->last_communication_at = now();

                    if ($owner instanceof User) {
                        $thread->owner_type = $owner->getMorphClass();
                        $thread->owner_id = $owner->getKey();
                    }

                    $thread->save();
                });

                file_put_contents($workdir . '/winner-inserted', (string) getmypid());

                $observed = communicationsEngineWaitForTransactionLock();

                if (! $observed) {
                    throw new RuntimeException('Engine race never observed a transactionid lock wait.');
                }

                DB::commit();

                return ['committed' => true, 'observed_lock' => true];
            } catch (Throwable $exception) {
                DB::rollBack();

                throw $exception;
            }
        },
        'loser' => function (string $workdir) use ($channel, $externalThreadId, $owner): array {
            DB::connection()->getPdo();
            file_put_contents($workdir . '/loser-connected', (string) getmypid());
            communicationsEngineWaitForFile($workdir . '/winner-inserted');

            return DB::transaction(function () use ($channel, $externalThreadId, $owner): array {
                DB::table('communications_engine_markers')->insert([
                    'id' => (string) Str::uuid(),
                    'marker' => 'before',
                ]);

                $thread = OwnerContext::withOwner($owner, fn (): CommunicationThread => app(ResolveCommunicationThreadAction::class)->handle(
                    channel: $channel,
                    externalThreadId: $externalThreadId,
                    title: 'Resolved by loser',
                ));

                DB::table('communications_engine_markers')->insert([
                    'id' => (string) Str::uuid(),
                    'marker' => 'after',
                ]);

                return [
                    'thread_id' => $thread->id,
                    'title' => $thread->title,
                    'owner_type' => $thread->owner_type,
                    'owner_id' => $thread->owner_id,
                ];
            });
        },
    ]);

    return [
        'results' => $results,
        'channel' => $channel,
        'external_thread_id' => $externalThreadId,
        'owner' => $owner,
    ];
}

it('converges same-owner and explicit-global thread contention without losing caller markers', function (): void {
    $this->useEngine();

    foreach (['owner', 'global'] as $mode) {
        $race = communicationsEngineRunContention($mode);
        $results = $race['results'];
        $owner = $race['owner'];

        expect($results['winner'])->toBe(['committed' => true, 'observed_lock' => true]);

        $thread = CommunicationThread::query()
            ->withoutOwnerScope()
            ->where('channel', $race['channel'])
            ->where('external_thread_id', $race['external_thread_id'])
            ->sole();

        expect($thread->title)->toBe('Resolved by loser')
            ->and(DB::table('communications_engine_markers')->count())->toBe(2);

        if ($mode === 'owner') {
            expect($owner)->toBeInstanceOf(User::class)
                ->and($thread->owner_type)->toBe($owner->getMorphClass())
                ->and((string) $thread->owner_id)->toBe((string) $owner->getKey());
        } else {
            expect($thread->owner_type)->toBeNull()
                ->and($thread->owner_id)->toBeNull();
        }

        expect($results['loser']['thread_id'])->toBe($thread->id);

        DB::table('communications_engine_markers')->delete();
    }
});

it('keeps owned and global engine partitions distinct while allowing null external ids', function (): void {
    $this->useEngine();

    $firstOwner = communicationsEngineOwner('first');
    $secondOwner = communicationsEngineOwner('second');
    $externalThreadId = 'thread-partitions-' . Str::uuid();
    $action = app(ResolveCommunicationThreadAction::class);

    $global = OwnerContext::withOwner(null, fn (): CommunicationThread => $action->handle(
        channel: 'engine',
        externalThreadId: $externalThreadId,
        title: 'Global partition',
    ));
    $first = OwnerContext::withOwner($firstOwner, fn (): CommunicationThread => $action->handle(
        channel: 'engine',
        externalThreadId: $externalThreadId,
        title: 'First partition',
    ));
    $second = OwnerContext::withOwner($secondOwner, fn (): CommunicationThread => $action->handle(
        channel: 'engine',
        externalThreadId: $externalThreadId,
        title: 'Second partition',
    ));
    $firstAgain = OwnerContext::withOwner($firstOwner, fn (): CommunicationThread => $action->handle(
        channel: 'engine',
        externalThreadId: $externalThreadId,
        title: 'First partition updated',
    ));

    expect($firstAgain->id)->toBe($first->id)
        ->and($second->id)->not->toBe($first->id)
        ->and($global->id)->not->toBe($first->id)
        ->and($global->fresh()->title)->toBe('Global partition');

    $firstNull = OwnerContext::withOwner($firstOwner, fn (): CommunicationThread => $action->handle(channel: 'engine'));
    $secondNull = OwnerContext::withOwner($firstOwner, fn (): CommunicationThread => $action->handle(channel: 'engine'));

    expect($secondNull->id)->not->toBe($firstNull->id)
        ->and(CommunicationThread::query()->withoutOwnerScope()->whereNull('external_thread_id')->count())->toBe(2);
});

it('rethrows and requires caller retry when repeatable read cannot see the committed winner', function (): void {
    $this->useEngine();

    $owner = communicationsEngineOwner('repeatable');
    $channel = 'engine';
    $externalThreadId = 'thread-repeatable-' . Str::uuid();

    $results = EngineRace::run([
        'loser' => function (string $workdir) use ($channel, $externalThreadId, $owner): array {
            $connection = DB::connection();
            $connection->beginTransaction();

            try {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                $connection->selectOne('select 1 as snapshot');

                file_put_contents($workdir . '/loser-snapshot', (string) getmypid());

                communicationsEngineWaitForFile($workdir . '/winner-committed');

                DB::table('communications_engine_markers')->insert([
                    'id' => (string) Str::uuid(),
                    'marker' => 'before',
                ]);

                try {
                    OwnerContext::withOwner($owner, fn (): CommunicationThread => app(ResolveCommunicationThreadAction::class)->handle(
                        channel: $channel,
                        externalThreadId: $externalThreadId,
                    ));

                    return ['resolved' => true, 'error' => null];
                } catch (Throwable $exception) {
                    return ['resolved' => false, 'error' => $exception::class];
                }
            } finally {
                $connection->rollBack();
            }
        },
        'winner' => function (string $workdir) use ($channel, $externalThreadId, $owner): array {
            communicationsEngineWaitForFile($workdir . '/loser-snapshot');

            OwnerContext::withOwner($owner, function () use ($channel, $externalThreadId, $owner): void {
                $thread = new CommunicationThread;
                $thread->channel = $channel;
                $thread->external_thread_id = $externalThreadId;
                $thread->status = 'open';
                $thread->opened_at = now();
                $thread->last_communication_at = now();
                $thread->owner_type = $owner->getMorphClass();
                $thread->owner_id = $owner->getKey();
                $thread->save();
            });

            file_put_contents($workdir . '/winner-committed', (string) getmypid());

            return ['committed' => true];
        },
    ]);

    expect($results['loser']['resolved'])->toBeFalse()
        ->and($results['loser']['error'])->toBe(UniqueConstraintViolationException::class)
        ->and($results['winner'])->toBe(['committed' => true])
        ->and(DB::table('communications_engine_markers')->count())->toBe(0)
        ->and(CommunicationThread::query()
            ->withoutOwnerScope()
            ->where('channel', $channel)
            ->where('external_thread_id', $externalThreadId)
            ->count())->toBe(1);
});
