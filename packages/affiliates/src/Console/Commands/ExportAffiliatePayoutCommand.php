<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
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
        $payout = $this->resolvePayout($reference);

        if (! $payout) {
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

        $path = $this->option('path') ?: storage_path("payouts/{$payout->reference}.csv");
        @mkdir(dirname($path), recursive: true);
        file_put_contents($path, $csv->toString());

        $this->info("Exported payout to {$path}");

        return self::SUCCESS;
    }

    private function resolvePayout(string $reference): ?AffiliatePayout
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return $this->queryPayout($reference);
        }

        $owner = OwnerContext::resolve();
        if ($owner !== null) {
            return $this->queryPayout($reference);
        }

        $columns = OwnerTupleColumns::forModelClass(AffiliatePayout::class);

        $owners = AffiliatePayout::query()
            ->withoutOwnerScope()
            ->select([$columns->ownerTypeColumn, $columns->ownerIdColumn])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return OwnerContext::withOwner(null, fn (): ?AffiliatePayout => $this->queryPayout($reference));
        }

        $includeGlobal = (bool) config('affiliates.owner.include_global', false);
        if ($includeGlobal) {
            config()->set('affiliates.owner.include_global', false);
        }

        $processedGlobal = false;

        try {
            foreach ($owners as $row) {
                $parsed = OwnerTupleParser::fromRow($row, $columns);

                if ($parsed->isExplicitGlobal()) {
                    if ($processedGlobal) {
                        continue;
                    }

                    $processedGlobal = true;
                }

                $payout = OwnerContext::withOwner(
                    $parsed->toOwnerModel(),
                    fn (): ?AffiliatePayout => $this->queryPayout($reference)
                );

                if ($payout !== null) {
                    return $payout;
                }
            }
        } finally {
            if ($includeGlobal) {
                config()->set('affiliates.owner.include_global', true);
            }
        }

        return null;
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
