<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Commerce\Tests\AffiliateCashPosting\CashPostingTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

uses(CashPostingTestCase::class);

/**
 * @param  list<string>  $affiliateIds
 * @return array{dir: string, results: list<array<string, mixed>>, outputs: list<string>, exits: list<int|null>}
 */
function runCashPostRace(CashPostingTestCase $test, string $key, array $affiliateIds, int $amount, string $currency): array
{
    $dir = sys_get_temp_dir() . '/cashpost-race-' . str_replace('-', '', (string) Str::uuid());
    mkdir($dir, 0777, true);

    $gate = $dir . '/gate';
    touch($gate);

    $worker = __DIR__ . '/Support/cash-post-race-worker.php';
    $processes = [];
    $resultFiles = [];

    foreach ($affiliateIds as $index => $affiliateId) {
        $ready = "{$dir}/ready-{$index}";
        $result = "{$dir}/result-{$index}.json";
        $resultFiles[] = $result;

        $process = new Process([
            PHP_BINARY,
            $worker,
            '--driver=' . $test->cashPostDriverName(),
            '--db=' . $test->cashPostDatabaseName(),
            '--key=' . $key,
            '--affiliate=' . $affiliateId,
            '--amount=' . (string) $amount,
            '--currency=' . $currency,
            '--ready=' . $ready,
            '--gate=' . $gate,
            '--result=' . $result,
        ], __DIR__ . '/../../../', null);

        $process->setTimeout(180);
        $process->start();
        $processes[] = $process;
    }

    $deadline = microtime(true) + 120;

    foreach ($affiliateIds as $index => $affiliateId) {
        while (! file_exists("{$dir}/ready-{$index}")) {
            $crashed = array_filter($processes, fn (Process $p): bool => ! $p->isRunning());

            if ($crashed !== []) {
                break 2;
            }

            if (microtime(true) > $deadline) {
                break 2;
            }

            usleep(10000);
        }
    }

    if (file_exists($gate)) {
        unlink($gate);
    }

    $outputs = [];
    $exits = [];

    foreach ($processes as $index => $process) {
        $process->wait();
        $exits[] = $process->getExitCode();
        $outputs[] = "worker-{$index} exit={$process->getExitCode()} out=" . mb_substr($process->getOutput(), 0, 500) . ' err=' . mb_substr($process->getErrorOutput(), 0, 1000);
    }

    $results = [];

    foreach ($resultFiles as $file) {
        $decoded = file_exists($file) ? json_decode((string) file_get_contents($file), true) : null;
        $results[] = is_array($decoded) ? $decoded : ['status' => 'missing-result'];
    }

    return ['dir' => $dir, 'results' => $results, 'outputs' => $outputs, 'exits' => $exits];
}

function cleanupCashPostRace(string $dir): void
{
    foreach ((array) glob($dir . '/*') as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    if (is_dir($dir)) {
        rmdir($dir);
    }
}

test('concurrent same-recipient workers commit one award and replay the rest', function (): void {
    $recipient = $this->makeRecipient();
    $key = $this->awardKey('race-same');

    $run = runCashPostRace($this, $key, [
        (string) $recipient->getKey(),
        (string) $recipient->getKey(),
        (string) $recipient->getKey(),
    ], 1100, 'MYR');

    try {
        $statuses = array_column($run['results'], 'status');

        expect($run['exits'])->toHaveCount(3, 'every worker must report a real exit: ' . json_encode($run));

        foreach ($run['exits'] as $exit) {
            expect($exit)->toBe(0, 'worker must exit cleanly: ' . json_encode($run));
        }

        expect($statuses)->toHaveCount(3)
            ->and($statuses)->each->toBeIn(['created', 'replayed'], 'no missing or crashed worker results: ' . json_encode($run))
            ->and(array_count_values($statuses)['created'] ?? 0)->toBe(1, 'expected exactly one winner: ' . json_encode($run))
            ->and(array_count_values($statuses)['replayed'] ?? 0)->toBe(2, 'expected two replays: ' . json_encode($run));

        $conversionIds = array_values(array_unique(array_filter(array_column($run['results'], 'conversion_id'))));
        expect($conversionIds)->toHaveCount(1);

        expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(1);

        $balance = AffiliateBalance::query()
            ->where('affiliate_id', $recipient->getKey())
            ->where('currency', 'MYR')
            ->firstOrFail();
        expect((int) $balance->available_minor)->toBe(1100)
            ->and((int) $balance->lifetime_earnings_minor)->toBe(1100)
            ->and(AffiliateFraudSignal::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0);
    } finally {
        cleanupCashPostRace($run['dir']);
    }
});

test('concurrent rival recipients racing one key produce one winner and explicit mismatches', function (): void {
    $first = $this->makeRecipient();
    $second = $this->makeRecipient();
    $third = $this->makeRecipient();
    $key = $this->awardKey('race-rival');

    $run = runCashPostRace($this, $key, [
        (string) $first->getKey(),
        (string) $second->getKey(),
        (string) $third->getKey(),
    ], 1300, 'MYR');

    try {
        $statuses = array_column($run['results'], 'status');

        expect($run['exits'])->toHaveCount(3, 'every worker must report a real exit: ' . json_encode($run));

        foreach ($run['exits'] as $exit) {
            expect($exit)->toBe(0, 'worker must exit cleanly: ' . json_encode($run));
        }

        expect($statuses)->toHaveCount(3)
            ->and($statuses)->each->toBeIn(['created', 'exception'], 'no missing or crashed worker results: ' . json_encode($run))
            ->and(array_count_values($statuses)['created'] ?? 0)->toBe(1, 'expected exactly one winner: ' . json_encode($run))
            ->and(array_count_values($statuses)['exception'] ?? 0)->toBe(2, 'expected two mismatches: ' . json_encode($run));

        $classes = array_column(array_filter($run['results'], fn ($row): bool => ($row['status'] ?? null) === 'exception'), 'class');
        expect(array_unique($classes))->toBe([InvalidArgumentException::class]);

        $messages = array_column(array_filter($run['results'], fn ($row): bool => ($row['status'] ?? null) === 'exception'), 'message');

        foreach ($messages as $message) {
            expect((string) $message)->not->toContain((string) $first->getKey())
                ->and((string) $message)->not->toContain((string) $second->getKey())
                ->and((string) $message)->not->toContain((string) $third->getKey());
        }

        $rows = AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->get();
        expect($rows)->toHaveCount(1);

        $winnerId = (string) $rows->sole()->affiliate_id;
        expect($winnerId)->toBeIn([(string) $first->getKey(), (string) $second->getKey(), (string) $third->getKey()]);

        $totalAvailable = 0;

        foreach ([$first, $second, $third] as $candidate) {
            $balance = AffiliateBalance::query()
                ->where('affiliate_id', $candidate->getKey())
                ->where('currency', 'MYR')
                ->first();

            $totalAvailable += $balance === null ? 0 : (int) $balance->available_minor;

            if ((string) $candidate->getKey() === $winnerId) {
                expect($balance)->not->toBeNull()
                    ->and((int) $balance->available_minor)->toBe(1300);
            } else {
                expect($balance)->toBeNull();
            }
        }

        expect($totalAvailable)->toBe(1300);
    } finally {
        cleanupCashPostRace($run['dir']);
    }
});

test('concurrent deactivation during posting is observed instead of slipped through', function (): void {
    $recipient = $this->makeRecipient();
    $key = $this->awardKey('interleave-deactivate');
    $affiliateId = (string) $recipient->getKey();

    $dir = sys_get_temp_dir() . '/cashpost-interleave-' . str_replace('-', '', (string) Str::uuid());
    mkdir($dir, 0777, true);

    $gate = $dir . '/gate';
    touch($gate);

    $ready = $dir . '/ready';
    $resultFile = $dir . '/result.json';

    $process = new Process([
        PHP_BINARY,
        __DIR__ . '/Support/cash-post-race-worker.php',
        '--driver=' . $this->cashPostDriverName(),
        '--db=' . $this->cashPostDatabaseName(),
        '--key=' . $key,
        '--affiliate=' . $affiliateId,
        '--amount=1400',
        '--currency=MYR',
        '--ready=' . $ready,
        '--gate=' . $gate,
        '--result=' . $resultFile,
    ], __DIR__ . '/../../../', null);
    $process->setTimeout(120);

    DB::beginTransaction();

    try {
        $locked = Affiliate::query()->whereKey($affiliateId)->lockForUpdate()->firstOrFail();
        expect($locked->canBeAttributed())->toBeTrue();

        $process->start();

        $deadline = microtime(true) + 60;

        while (! file_exists($ready)) {
            if (! $process->isRunning()) {
                break;
            }

            if (microtime(true) > $deadline) {
                break;
            }

            usleep(10000);
        }

        expect(file_exists($ready))->toBeTrue('worker never reached the gate: ' . mb_substr($process->getErrorOutput(), 0, 1000));

        unlink($gate);

        sleep(3);

        expect($process->isRunning())->toBeTrue('worker must block on the held recipient lock instead of deciding from a stale read');

        $locked->status->transitionTo(Disabled::class);

        DB::commit();

        $process->wait();

        expect($process->getExitCode())->toBe(0, 'worker must exit cleanly: out=' . mb_substr($process->getOutput(), 0, 500) . ' err=' . mb_substr($process->getErrorOutput(), 0, 1000));

        $decoded = file_exists($resultFile) ? json_decode((string) file_get_contents($resultFile), true) : null;
        expect(is_array($decoded) ? ($decoded['status'] ?? 'missing-status') : 'missing-result')->toBe('no_affiliate');

        expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(0);

        $balance = AffiliateBalance::query()
            ->where('affiliate_id', $affiliateId)
            ->where('currency', 'MYR')
            ->first();
        expect($balance)->toBeNull();
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        if ($process->isRunning()) {
            $process->stop();
        }

        cleanupCashPostRace($dir);
    }
});
