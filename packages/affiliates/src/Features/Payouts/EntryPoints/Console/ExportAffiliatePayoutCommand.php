<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\EntryPoints\Console;

use AIArmada\Affiliates\Contracts\PayoutCsvWriter;
use AIArmada\Affiliates\Features\Payouts\Queries\ReadPayoutExport;
use Illuminate\Console\Command;
use Throwable;

final class ExportAffiliatePayoutCommand extends Command
{
    protected $signature = 'affiliates:payout:export {payout : Payout reference or ID} {--path= : Optional path to save CSV}';

    protected $description = 'Export an affiliate payout with conversions to CSV for payment processors';

    public function handle(ReadPayoutExport $query, PayoutCsvWriter $writer): int
    {
        $reference = (string) $this->argument('payout');
        $path = (string) ($this->option('path') ?: storage_path('payouts/' . basename($reference) . '.csv'));

        try {
            $rows = $query->find($reference);
        } catch (Throwable) {
            $this->error('Payout lookup failed or the reference is ambiguous.');

            return self::FAILURE;
        }

        if ($rows === null) {
            $this->error('Payout not found.');

            return self::FAILURE;
        }

        try {
            $writer->write($path, $rows);
        } catch (Throwable) {
            $this->error('Payout CSV could not be written.');

            return self::FAILURE;
        }

        $this->info("Exported payout to {$path}");

        return self::SUCCESS;
    }
}
