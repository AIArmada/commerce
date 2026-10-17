<?php

declare(strict_types=1);

// Permanent race worker for RewardCoreRaceTest. Executed only as a standalone
// OS process (`php RewardCoreRaceWorker.php --database=... ...`); returns
// immediately when merely included, so test runners never execute it.

use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\PublishRewardProgramVersionAction;
use AIArmada\Rewards\Actions\ReacceptRewardTermsAction;
use AIArmada\Rewards\Tests\Support\RewardRaceWorkerBoot;
use AIArmada\Rewards\Tests\Core\Support\RewardCoreWorkerDb;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$options = getopt('', [
    'database:',
    'mode:',
    'ready-dir:',
    'slot:',
]);

$fail = static function (string $message): never {
    echo json_encode(['ok' => false, 'error' => $message], JSON_THROW_ON_ERROR) . "\n";

    exit(1);
};

$database = (string) ($options['database'] ?? '');
$mode = (string) ($options['mode'] ?? '');
$readyDir = (string) ($options['ready-dir'] ?? '');
$slot = (int) ($options['slot'] ?? -1);

if (preg_match('/^share_rewards_test_rewards_core_[a-z0-9_]{1,30}$/', $database) !== 1) {
    $fail('Refusing a database outside the isolated rewards-core namespace.');
}

if (! in_array($mode, ['publish', 'enroll', 'reaccept'], true) || $readyDir === '' || ! in_array($slot, [0, 1], true)) {
    $fail('Missing race job arguments.');
}

$payloadFile = $readyDir . '/payload.json';

if (! is_file($payloadFile)) {
    $fail('Missing race payload.');
}

$payload = json_decode((string) file_get_contents($payloadFile), true);

if (! is_array($payload)) {
    $fail('Race payload must be JSON.');
}

$basePath = realpath(__DIR__ . '/../../../../');

if (! is_string($basePath) || ! is_file($basePath . '/vendor/autoload.php')) {
    $fail('Unable to locate the monorepo base path.');
}

require $basePath . '/vendor/autoload.php';

(new RewardRaceWorkerBoot('worker'))->bootForWorker();

$workerConfig = RewardCoreWorkerDb::connectionConfig($database);

config(['database.connections.rewards_core_race' => $workerConfig]);
DB::purge('rewards_core_race');
DB::setDefaultConnection('rewards_core_race');

// File barrier: both slots start the critical section together.
file_put_contents($readyDir . '/ready-' . $slot, (string) $slot);

$deadline = microtime(true) + 25.0;

while ((! is_file($readyDir . '/ready-0') || ! is_file($readyDir . '/ready-1')) && microtime(true) < $deadline) {
    usleep(20000);
}

if (! is_file($readyDir . '/ready-0') || ! is_file($readyDir . '/ready-1')) {
    $fail('Race barrier timed out.');
}

usleep(50000 * $slot);

try {
    $result = match ($mode) {
        'publish' => (function () use ($payload): array {
            $effective = CarbonImmutable::parse((string) $payload['effective_from']);
            $version = app(PublishRewardProgramVersionAction::class)->handle(
                (string) $payload['program_id'],
                $effective,
                (array) $payload['rules'],
                (array) $payload['terms'],
                null,
                null,
                null,
                $effective,
            );

            return ['ok' => true, 'version_id' => (string) $version->getKey()];
        })(),
        'enroll' => (function () use ($payload): array {
            $enrollment = app(EnrollRewardMemberAction::class)->handle(
                (string) $payload['program_id'],
                'user',
                (string) $payload['member_id'],
                (string) $payload['version_id'],
                'api',
                CarbonImmutable::parse((string) $payload['decision_time']),
            );

            return ['ok' => true, 'enrollment_id' => (string) $enrollment->getKey()];
        })(),
        'reaccept' => (function () use ($payload, $slot): array {
            $acceptance = app(ReacceptRewardTermsAction::class)->handle(
                (string) $payload['enrollment_id'],
                (string) $payload['version_id'],
                'api',
                CarbonImmutable::parse((string) $payload['decision_time'])->addSeconds($slot),
            );

            return ['ok' => true, 'acceptance_id' => (string) $acceptance->getKey(), 'seq' => $acceptance->seq];
        })(),
    };

    echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";

    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => mb_substr($exception->getMessage(), 0, 300),
        'conflict' => str_contains($exception->getMessage(), 'Concurrent publication conflict')
            || str_contains($exception->getMessage(), 'strictly after'),
    ], JSON_THROW_ON_ERROR) . "\n";

    exit(0);
}
