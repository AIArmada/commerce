<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
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

        $owners = AffiliatePayout::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return $this->queryPayout($reference);
        }

        foreach ($owners as $row) {
            $owner = $this->resolveOwnerFromRow($row);

            $payout = OwnerContext::withOwner($owner, fn (): ?AffiliatePayout => $this->queryPayout($reference));

            if ($payout !== null) {
                return $payout;
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

    private function resolveOwnerFromRow(object $row): ?Model
    {
        $ownerType = $row->owner_type ?? null;
        $ownerId = $row->owner_id ?? null;

        return OwnerContext::fromTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            is_string($ownerId) || is_int($ownerId) ? $ownerId : null
        );
    }
}
