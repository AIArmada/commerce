<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\ProgramLinkVolume;
use AIArmada\Affiliates\Features\Conversions\Api\LinkConversionReader;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\PaidConversion;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReferenceReader;
use AIArmada\Affiliates\Support\RevenueVolume;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use LogicException;

final readonly class ReadLinkConversions implements LinkConversionReader
{
    public function hasLinkHistory(string $linkId, int $connectionIdentity): bool
    {
        $connection = $this->connection($connectionIdentity);

        if (! Str::isUuid($linkId)) {
            return false;
        }

        $attributions = $connection->table((string) config('affiliates.database.tables.attributions', 'affiliate_attributions'))
            ->where('affiliate_link_id', $linkId)->select('id');

        return AffiliateConversion::query()->withoutOwnerScope()
            ->where(function (Builder $query) use ($linkId, $attributions): void {
                $query->where('affiliate_link_id', $linkId)->orWhereIn('affiliate_attribution_id', $attributions);
            })->exists();
    }

    public function programVolume(string $affiliateId, string $programId, string $fallbackCurrency, int $connectionIdentity, ?DateTimeImmutable $since = null, bool $approvedOnly = false): ProgramLinkVolume
    {
        $connection = $this->connection($connectionIdentity);

        if (! Str::isUuid($affiliateId) || ! Str::isUuid($programId)) {
            return new ProgramLinkVolume(0, []);
        }

        $affiliate = app(AffiliateReferenceReader::class)->ownerReference($affiliateId, $connectionIdentity, includeGlobal: true)
            ?? throw new ModelNotFoundException('Affiliate not found.');
        $links = $connection->table((string) config('affiliates.database.tables.links', 'affiliate_links'))
            ->where('affiliate_id', $affiliateId)->where('program_id', $programId)->select('id');
        $attributions = $connection->table((string) config('affiliates.database.tables.attributions', 'affiliate_attributions'))
            ->where('affiliate_id', $affiliateId)->whereIn('affiliate_link_id', clone $links)->select('id');

        $query = AffiliateConversion::query()->where('affiliate_id', $affiliateId);

        if ((bool) config('affiliates.owner.enabled', false)) {
            $attributions->where('owner_type', $affiliate->ownerType)->where('owner_id', $affiliate->ownerId);
            $query->where('owner_type', $affiliate->ownerType)->where('owner_id', $affiliate->ownerId);
        }

        $query->where(function (Builder $query) use ($links, $attributions): void {
            $query->whereIn('affiliate_link_id', $links)->orWhereIn('affiliate_attribution_id', $attributions);
        });

        if ($since !== null) {
            $query->where('occurred_at', '>=', $since);
        }

        if ($approvedOnly) {
            $query->whereIn('status', [ApprovedConversion::value(), PaidConversion::value()]);
        }

        $rows = $query->toBase()
            ->selectRaw('commission_currency as currency, COUNT(*) as conversion_count, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as total')
            ->groupBy('commission_currency')->get();

        return new ProgramLinkVolume((int) $rows->sum('conversion_count'), RevenueVolume::foldRows($rows, $fallbackCurrency));
    }

    private function connection(int $connectionIdentity): ConnectionInterface
    {
        $connection = (new AffiliateConversion)->getConnection();

        app(AffiliateAccessGuard::class)->assertConnection($connectionIdentity);

        if (spl_object_id($connection) !== $connectionIdentity) {
            throw new LogicException('Link conversion reads require a shared database connection.');
        }

        return $connection;
    }
}
