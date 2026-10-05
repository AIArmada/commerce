<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Signals\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Genuine multi-process race runner for engine concurrency tests.
 *
 * Each worker runs in its own forked process with freshly reconnected,
 * independent database connections. Workers rendezvous on an explicit
 * file barrier (every worker waits for every other worker's ready file)
 * before executing, so overlap is structural rather than sleep-based.
 *
 * The caller must not hold an open database transaction: parent PDO
 * connections are disconnected before forking so children can never close
 * or poison an inherited handle, and every side reconnects independently.
 */
final class EngineRace
{
    /**
     * @param  array<string, Closure(string $workdir): mixed>  $workers  role => closure returning JSON-encodable data
     * @return array<string, mixed> role => worker result
     */
    public static function run(array $workers, int $timeoutSeconds = 90): array
    {
        if (count($workers) < 1) {
            throw new RuntimeException('EngineRace requires at least one worker.');
        }

        if (! function_exists('pcntl_fork')) {
            throw new RuntimeException('EngineRace requires the pcntl extension.');
        }

        foreach (DB::getConnections() as $name => $connection) {
            if ($connection->transactionLevel() > 0) {
                throw new RuntimeException('EngineRace requires no open parent transaction.');
            }

            DB::disconnect((string) $name);
        }

        $roles = array_keys($workers);
        $workdir = sys_get_temp_dir() . '/sigeng-race-' . Str::uuid()->toString();

        if (! mkdir($workdir) && ! is_dir($workdir)) {
            throw new RuntimeException('EngineRace could not create workdir.');
        }

        /** @var array<string, int> $children role => pid */
        $children = [];
        $awaitStarted = false;

        try {
            foreach ($workers as $role => $work) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    throw new RuntimeException('EngineRace failed to fork worker [' . $role . '].');
                }

                if ($pid === 0) {
                    self::child($workdir, (string) $role, $work, $roles, $timeoutSeconds);
                }

                $children[(string) $role] = $pid;
            }

            $awaitStarted = true;

            return self::await($workdir, $children, $timeoutSeconds);
        } catch (Throwable $e) {
            if (! $awaitStarted) {
                self::killAndReap($children);
            }

            throw $e;
        } finally {
            self::removeDirectory($workdir);
        }
    }

    /**
     * @param  Closure(string $workdir): mixed  $work
     * @param  list<string>  $roles
     */
    private static function child(string $workdir, string $role, Closure $work, array $roles, int $timeoutSeconds): never
    {
        try {
            foreach (array_keys(config('database.connections', [])) as $connection) {
                DB::purge((string) $connection);
            }

            file_put_contents($workdir . '/ready-' . $role, (string) getmypid());

            $deadline = microtime(true) + $timeoutSeconds;

            while (true) {
                $missing = array_filter(
                    $roles,
                    static fn (string $candidate): bool => ! is_file($workdir . '/ready-' . $candidate)
                );

                if ($missing === []) {
                    break;
                }

                if (microtime(true) > $deadline) {
                    file_put_contents(
                        $workdir . '/result-' . $role . '.json',
                        (string) json_encode(['ok' => false, 'error' => 'barrier timeout waiting for: ' . implode(',', $missing)], JSON_THROW_ON_ERROR)
                    );

                    exit(3);
                }

                usleep(1000);
            }

            $result = $work($workdir);

            file_put_contents(
                $workdir . '/result-' . $role . '.json',
                (string) json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR)
            );

            exit(0);
        } catch (Throwable $e) {
            file_put_contents(
                $workdir . '/result-' . $role . '.json',
                (string) json_encode([
                    'ok' => false,
                    'error' => $e::class . ': ' . $e->getMessage(),
                    'trace' => mb_substr($e->getTraceAsString(), 0, 4000),
                ], JSON_THROW_ON_ERROR)
            );

            exit(1);
        }
    }

    /**
     * @param  array<string, int>  $children  role => pid
     * @return array<string, mixed>
     */
    private static function await(string $workdir, array $children, int $timeoutSeconds): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $pending = $children;
        $failures = [];

        while ($pending !== []) {
            foreach ($pending as $role => $pid) {
                $status = 0;
                $reaped = pcntl_waitpid($pid, $status, WNOHANG);

                if ($reaped === 0) {
                    continue;
                }

                if ($reaped === -1) {
                    unset($pending[$role]);
                    $failures[$role] = self::readResultFile($workdir, $role);

                    continue;
                }

                unset($pending[$role]);

                if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                    $failures[$role] = self::readResultFile($workdir, $role);
                }
            }

            if ($pending === []) {
                break;
            }

            if (microtime(true) > $deadline) {
                self::killAndReap($pending);

                throw new RuntimeException('EngineRace timed out waiting for workers: ' . implode(',', array_keys($pending)));
            }

            usleep(5000);
        }

        if ($failures !== []) {
            throw new RuntimeException('EngineRace worker failure: ' . json_encode($failures));
        }

        $results = [];

        foreach ($children as $role => $pid) {
            $decoded = self::readResultFile($workdir, (string) $role);

            if (! is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
                throw new RuntimeException('EngineRace missing result for worker [' . $role . ']: ' . json_encode($decoded));
            }

            $results[(string) $role] = $decoded['result'] ?? null;
        }

        return $results;
    }

    /**
     * Kill and reap only still-running owned children. Already-reaped PIDs
     * are never signalled, so a reused PID can never be killed.
     *
     * @param  array<string, int>  $children  role => pid
     */
    private static function killAndReap(array $children): void
    {
        foreach ($children as $pid) {
            $status = 0;
            $reaped = pcntl_waitpid($pid, $status, WNOHANG);

            if ($reaped !== 0) {
                continue;
            }

            if (function_exists('posix_kill')) {
                posix_kill($pid, 9);
            }

            pcntl_waitpid($pid, $status);
        }
    }

    private static function readResultFile(string $workdir, string $role): mixed
    {
        $path = $workdir . '/result-' . $role . '.json';

        if (! is_file($path)) {
            return null;
        }

        return json_decode((string) file_get_contents($path), true);
    }

    private static function removeDirectory(string $workdir): void
    {
        if (! is_dir($workdir)) {
            return;
        }

        foreach ((array) glob($workdir . '/*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($workdir);
    }
}
