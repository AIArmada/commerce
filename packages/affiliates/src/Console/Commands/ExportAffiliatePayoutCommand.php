<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Console\Command;
use League\Csv\Writer;
use SplTempFileObject;

final class ExportAffiliatePayoutCommand extends Command
{
    protected $signature = 'affiliates:payout:export {payout : Payout reference or ID} {--path= : Optional path to save CSV}';

    protected $description = 'Export an affiliate payout with conversions to CSV for payment processors';

    public function handle(): int
    {
        $reference = $this->argument('payout');
        $payout = AffiliatePayout::query()
            ->where('reference', $reference)
            ->orWhere('id', $reference)
            ->with('conversions')
            ->first();

        if (! $payout) {
            $this->error('Payout not found.');

            return self::FAILURE;
        }

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne(['affiliate_code', 'order_reference', 'commission_minor', 'currency', 'status']);

        foreach ($payout->conversions as $conversion) {
            $csv->insertOne([
                $conversion->affiliate_code,
                $conversion->order_reference,
                $conversion->commission_minor,
                $conversion->commission_currency,
                $conversion->status->value ?? (string) $conversion->status,
            ]);
        }

        $path = $this->option('path') ?: storage_path("payouts/{$payout->reference}.csv");
        @mkdir(dirname($path), recursive: true);
        file_put_contents($path, $csv->toString());

        $this->info("Exported payout to {$path}");

        return self::SUCCESS;
    }
}
