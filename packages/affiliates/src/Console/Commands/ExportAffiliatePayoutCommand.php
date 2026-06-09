<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
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
        $path = $this->option('path') ?: storage_path('payouts/' . $reference . '.csv');

        $runner = new OwnerBatchRunner(
            AffiliatePayout::class,
            ['enabled' => 'affiliates.owner.enabled', 'include_global' => 'affiliates.owner.include_global'],
        );

        $payout = $runner->run(fn (): ?AffiliatePayout => $this->queryPayout((string) $reference));

        if (! $payout instanceof AffiliatePayout) {
            $this->error('Payout not found.');

            return self::FAILURE;
        }

        $csv = Writer::createFromFileObject(new SplTempFileObject);
        $csv->insertOne(['affiliate_code', 'reference', 'commission_minor', 'currency', 'status']);

        foreach ($payout->conversions as $conversion) {
            $csv->insertOne([
                $conversion->affiliate_code,
                $conversion->external_reference,
                $conversion->commission_minor,
                $conversion->commission_currency,
                ConversionStatus::normalize($conversion->status),
            ]);
        }

        @mkdir(dirname($path), recursive: true);
        file_put_contents($path, $csv->toString());

        $this->info("Exported payout to {$path}");

        return self::SUCCESS;
    }

    private function queryPayout(string $reference): ?AffiliatePayout
    {
        return AffiliatePayout::query()
            ->forOwner()
            ->where(function ($query) use ($reference): void {
                $query->where('reference', $reference)
                    ->orWhere('id', $reference);
            })
            ->with('conversions')
            ->first();
    }
}
