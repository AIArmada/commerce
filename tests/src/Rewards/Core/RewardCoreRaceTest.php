<?php

declare(strict_types=1);

use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\PublishRewardProgramVersionAction;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Models\RewardEnrollment;
use AIArmada\Rewards\Models\RewardEnrollmentAcceptance;
use AIArmada\Rewards\Models\RewardEnrollmentPeriod;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramVersion;
use AIArmada\Rewards\Tests\Core\Support\RewardCoreTestFixtures;
use AIArmada\Rewards\Tests\Core\Support\RewardCoreWorkerDb;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    foreach (RewardCoreWorkerDb::driverConfigs() as $driver => $config) {
        $socket = @fsockopen((string) $config['host'], (int) $config['port'], $errno, $errstr, 2);

        if ($socket === false) {
            $this->markTestSkipped("Race fixtures unavailable: {$driver} at {$config['host']}:{$config['port']} refused.");
        }

        fclose($socket);
    }
});

function raceReadyDir(string $driver, string $case): string
{
    $token = ParallelTesting::token();
    $dir = sys_get_temp_dir() . '/rewards-core-race-' . ($token !== false ? $token : 'serial') . '-' . $driver . '-' . $case . '-' . getmypid();

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    foreach (glob($dir . '/ready-*') ?: [] as $stale) {
        unlink($stale);
    }

    return $dir;
}

/**
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function runRacePair(string $driver, string $database, string $mode, array $payload, string $case): array
{
    $readyDir = raceReadyDir($driver, $case);
    file_put_contents($readyDir . '/payload.json', json_encode($payload, JSON_THROW_ON_ERROR));

    $configs = RewardCoreWorkerDb::driverConfigs();
    $username = (string) $configs[$driver]['username'];
    $password = (string) $configs[$driver]['password'];
    $port = $driver === 'pgsql' ? 45432 : 43306;

    $worker = __DIR__ . '/RewardCoreRaceWorker.php';

    $make = fn (int $slot): Process => new Process([
        PHP_BINARY, $worker,
        '--driver=' . $driver,
        '--host=127.0.0.1',
        '--port=' . $port,
        '--database=' . $database,
        '--username=' . $username,
        '--password=' . $password,
        '--mode=' . $mode,
        '--ready-dir=' . $readyDir,
        '--slot=' . $slot,
    ], timeout: 120);

    $first = $make(0);
    $second = $make(1);
    $first->start();
    $second->start();
    $first->wait();
    $second->wait();

    $decode = static function (Process $process): array {
        $exit = $process->getExitCode();
        $output = mb_trim($process->getOutput());

        if ($output === '') {
            return ['ok' => false, 'exit' => $exit, 'error' => mb_substr(mb_trim($process->getErrorOutput()), 0, 300)];
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return ['ok' => false, 'exit' => $exit, 'error' => 'Worker output was not JSON.'];
        }

        $decoded['exit'] = $exit;

        return $decoded;
    };

    return [$decode($first), $decode($second)];
}

it('serializes concurrent same-family publishes on real drivers', function (string $driver): void {
    [$database, $previous] = RewardCoreWorkerDb::setUp($driver, 'publish');

    try {
        $program = RewardProgram::factory()->create([
            'family_key' => 'core-race-pub-' . $driver . '-' . Str::lower(Str::random(4)),
            'mode' => RewardProgramMode::Points,
        ]);

        $payload = [
            'program_id' => (string) $program->getKey(),
            'effective_from' => '2026-08-01T00:00:00Z',
            'rules' => RewardCoreTestFixtures::rules(),
            'terms' => RewardCoreTestFixtures::terms(),
        ];

        [$a, $b] = runRacePair($driver, $database, 'publish', $payload, 'publish');

        expect($a['exit'] ?? null)->toBe(0)
            ->and($b['exit'] ?? null)->toBe(0);

        $wins = array_filter([$a, $b], static fn (array $r): bool => ($r['ok'] ?? false) === true);

        expect($wins)->toHaveCount(1)
            ->and(RewardProgramVersion::query()->where('program_id', $program->getKey())->count())->toBe(1)
            ->and($program->refresh()->publications()->count())->toBe(1);

        $loser = ($a['ok'] ?? false) === true ? $b : $a;
        expect($loser['conflict'] ?? false)->toBeTrue();
    } finally {
        RewardCoreWorkerDb::tearDown($driver, $database, $previous);
    }
})->with(['pgsql', 'mariadb']);

it('keeps one canonical enrollment under concurrent same-member enrolls', function (string $driver): void {
    [$database, $previous] = RewardCoreWorkerDb::setUp($driver, 'enroll');

    try {
        $program = RewardProgram::factory()->create([
            'family_key' => 'core-race-enr-' . $driver . '-' . Str::lower(Str::random(4)),
            'mode' => RewardProgramMode::Points,
        ]);
        $effective = CarbonImmutable::parse('2026-06-01T00:00:00Z');
        $version = PublishRewardProgramVersionAction::run(
            $program,
            $effective,
            RewardCoreTestFixtures::rules(),
            RewardCoreTestFixtures::terms(),
            null,
            null,
            null,
            $effective,
        );

        $memberId = (string) Str::uuid();

        $payload = [
            'program_id' => (string) $program->getKey(),
            'member_id' => $memberId,
            'version_id' => (string) $version->getKey(),
            'decision_time' => '2026-07-05T00:00:00Z',
        ];

        [$a, $b] = runRacePair($driver, $database, 'enroll', $payload, 'enroll');

        expect($a['exit'] ?? null)->toBe(0)
            ->and($b['exit'] ?? null)->toBe(0)
            ->and($a['ok'] ?? false)->toBeTrue()
            ->and($b['ok'] ?? false)->toBeTrue()
            ->and($a['enrollment_id'] ?? null)->toBe($b['enrollment_id'] ?? null);

        $enrollment = RewardEnrollment::query()->where('member_id', $memberId)->firstOrFail();

        expect(RewardEnrollment::query()->where('program_id', $program->getKey())->count())->toBe(1)
            ->and(RewardEnrollmentAcceptance::query()->where('enrollment_id', $enrollment->getKey())->count())->toBe(1)
            ->and(RewardEnrollmentPeriod::query()->where('enrollment_id', $enrollment->getKey())->count())->toBe(1);
    } finally {
        RewardCoreWorkerDb::tearDown($driver, $database, $previous);
    }
})->with(['pgsql', 'mariadb']);

it('assigns unique acceptance seqs under concurrent reaccepts', function (string $driver): void {
    [$database, $previous] = RewardCoreWorkerDb::setUp($driver, 'reaccept');

    try {
        $program = RewardProgram::factory()->create([
            'family_key' => 'core-race-rea-' . $driver . '-' . Str::lower(Str::random(4)),
            'mode' => RewardProgramMode::Points,
        ]);
        $effective = CarbonImmutable::parse('2026-06-01T00:00:00Z');
        $version = PublishRewardProgramVersionAction::run(
            $program,
            $effective,
            RewardCoreTestFixtures::rules(),
            RewardCoreTestFixtures::terms(),
            null,
            null,
            null,
            $effective,
        );
        $enrollment = EnrollRewardMemberAction::run($program, 'user', (string) Str::uuid(), $version, 'api', CarbonImmutable::parse('2026-06-10T00:00:00Z'));

        $payload = [
            'enrollment_id' => (string) $enrollment->getKey(),
            'version_id' => (string) $version->getKey(),
            'decision_time' => '2026-07-06T00:00:00Z',
        ];

        [$a, $b] = runRacePair($driver, $database, 'reaccept', $payload, 'reaccept');

        expect($a['exit'] ?? null)->toBe(0)
            ->and($b['exit'] ?? null)->toBe(0)
            ->and($a['ok'] ?? false)->toBeTrue()
            ->and($b['ok'] ?? false)->toBeTrue();

        $seqs = RewardEnrollmentAcceptance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->orderBy('seq')
            ->pluck('seq')
            ->all();

        expect($seqs)->toBe([1, 2, 3]);

        $pointer = (string) $enrollment->refresh()->current_acceptance_id;
        $ids = RewardEnrollmentAcceptance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        expect($ids)->toContain($pointer);
    } finally {
        RewardCoreWorkerDb::tearDown($driver, $database, $previous);
    }
})->with(['pgsql', 'mariadb']);
