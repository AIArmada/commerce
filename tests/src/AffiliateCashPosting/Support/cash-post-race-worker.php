<?php

declare(strict_types=1);

/*
 * Standalone cash-post race worker. Boots the real harness against the
 * parent test's worker database in an independent OS process, waits on a
 * start gate alongside its siblings, then attempts one award with the
 * given identity. Writes a JSON result file. Not a Pest test file.
 */

use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateAward;
use AIArmada\Affiliates\Contracts\AffiliateAwardPolicy;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\AffiliateCashPosting\Support\CashPostingRaceWorkerBoot;
use AIArmada\Commerce\Tests\AffiliateCashPosting\Support\FixtureAffiliateAwardPolicy;

$options = getopt('', [
    'driver:',
    'db:',
    'key:',
    'affiliate:',
    'amount:',
    'currency:',
    'ready:',
    'gate:',
    'result:',
]);

$required = ['driver', 'db', 'key', 'affiliate', 'amount', 'currency', 'ready', 'gate', 'result'];

foreach ($required as $name) {
    if (! isset($options[$name]) || $options[$name] === '') {
        fwrite(STDERR, "cashpost-worker: missing --{$name}\n");
        exit(2);
    }
}

if (! preg_match('/^share_rewards_test_cash_post_[a-z0-9_]+$/', (string) $options['db'])) {
    fwrite(STDERR, "cashpost-worker: refused database [{$options['db']}]\n");
    exit(2);
}

if ($options['driver'] !== 'pgsql' && $options['driver'] !== 'mysql') {
    fwrite(STDERR, "cashpost-worker: refused driver [{$options['driver']}]\n");
    exit(2);
}

putenv('CASH_POST_DRIVER=' . $options['driver']);
putenv('CASH_POST_SKIP_MIGRATE=1');

require __DIR__ . '/../../../../vendor/autoload.php';

$writeResult = static function (array $payload) use ($options): void {
    file_put_contents((string) $options['result'], json_encode($payload, JSON_PRETTY_PRINT));
};

try {
    $case = new CashPostingRaceWorkerBoot('worker');
    $case->bootForWorker((string) $options['db']);

    app()->bind(AffiliateAwardPolicy::class, fn (): FixtureAffiliateAwardPolicy => FixtureAffiliateAwardPolicy::allow());

    $affiliate = Affiliate::query()->whereKey((string) $options['affiliate'])->first();

    if (! $affiliate instanceof Affiliate) {
        $writeResult(['status' => 'error', 'class' => 'affiliate_missing', 'message' => 'recipient not visible to worker']);
        exit(3);
    }

    file_put_contents((string) $options['ready'], (string) getmypid());

    $deadline = microtime(true) + 90;

    while (file_exists((string) $options['gate'])) {
        if (microtime(true) > $deadline) {
            $writeResult(['status' => 'error', 'class' => 'gate_timeout', 'message' => 'start gate never lifted']);
            exit(3);
        }

        usleep(5000);
    }

    try {
        $result = app(RecordAffiliateAward::class)->handle(
            $affiliate,
            (string) $options['key'],
            (int) $options['amount'],
            (string) $options['currency'],
        );

        $writeResult([
            'status' => $result->disposition->value,
            'conversion_id' => $result->conversion?->id,
        ]);
    } catch (Throwable $workerException) {
        $writeResult([
            'status' => 'exception',
            'class' => $workerException::class,
            'message' => mb_substr($workerException->getMessage(), 0, 200),
        ]);
    }

    exit(0);
} catch (Throwable $bootException) {
    fwrite(STDERR, 'cashpost-worker boot failed: ' . $bootException::class . ': ' . $bootException->getMessage() . "\n");
    $writeResult(['status' => 'error', 'class' => $bootException::class, 'message' => mb_substr($bootException->getMessage(), 0, 200)]);
    exit(3);
}
