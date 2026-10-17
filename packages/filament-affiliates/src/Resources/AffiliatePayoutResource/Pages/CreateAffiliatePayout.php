<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages;

use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CreateAffiliatePayout extends CreateRecord
{
    protected static string $resource = AffiliatePayoutResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $affiliate = $this->resolveAffiliate($data['affiliate_id'] ?? null);
        $conversionIds = $this->resolveConversionIds($data['conversion_ids'] ?? null);
        $this->assertConversionsBelongToAffiliate($conversionIds, $affiliate);

        $attributes = [];
        $notes = isset($data['notes']) && is_string($data['notes']) ? mb_trim($data['notes']) : '';
        $overrideReason = isset($data['payout_override_reason']) && is_string($data['payout_override_reason'])
            ? mb_trim($data['payout_override_reason'])
            : '';

        if ($notes !== '') {
            $attributes['metadata'] = ['notes' => $notes];
        }

        if (isset($data['scheduled_at'])) {
            $attributes['scheduled_at'] = $data['scheduled_at'];
        }

        if ($overrideReason !== '') {
            $attributes['payout_override_reason'] = $overrideReason;
        }

        try {
            return CreatePayout::run($conversionIds, $attributes);
        } catch (InvalidArgumentException | ModelNotFoundException $exception) {
            $field = str_contains($exception->getMessage(), 'cannot receive payouts')
                ? 'payout_override_reason'
                : 'conversion_ids';

            throw ValidationException::withMessages([
                'data.' . $field => $exception->getMessage(),
            ]);
        }
    }

    private function resolveAffiliate(mixed $affiliateId): Affiliate
    {
        if (! is_string($affiliateId) && ! is_int($affiliateId)) {
            throw ValidationException::withMessages([
                'data.affiliate_id' => 'The selected affiliate is invalid.',
            ]);
        }

        try {
            $affiliate = (bool) config('affiliates.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(
                    Affiliate::class,
                    (string) $affiliateId,
                    includeGlobal: (bool) config('affiliates.owner.include_global', false),
                    message: 'The selected affiliate is not accessible in the current owner scope.',
                )
                : Affiliate::query()->find((string) $affiliateId);
        } catch (AuthorizationException | InvalidArgumentException) {
            throw ValidationException::withMessages([
                'data.affiliate_id' => 'The selected affiliate is not accessible in the current owner scope.',
            ]);
        }

        if (! $affiliate instanceof Affiliate) {
            throw ValidationException::withMessages([
                'data.affiliate_id' => 'The selected affiliate is invalid.',
            ]);
        }

        if (! $affiliate->isCommercialPurpose()) {
            throw ValidationException::withMessages([
                'data.affiliate_id' => 'Only commercial-purpose affiliates can receive payouts.',
            ]);
        }

        return $affiliate;
    }

    /**
     * @return array<int, string>
     */
    private function resolveConversionIds(mixed $selectedIds): array
    {
        if (! is_array($selectedIds) || $selectedIds === []) {
            throw ValidationException::withMessages([
                'data.conversion_ids' => 'Select at least one approved conversion for this payout.',
            ]);
        }

        $conversionIds = [];

        foreach ($selectedIds as $selectedId) {
            if ((! is_string($selectedId) && ! is_int($selectedId)) || (string) $selectedId === '') {
                throw ValidationException::withMessages([
                    'data.conversion_ids' => 'One or more selected conversions are invalid.',
                ]);
            }

            $conversionIds[] = (string) $selectedId;
        }

        return array_values(array_unique($conversionIds));
    }

    /**
     * @param  array<int, string>  $conversionIds
     */
    private function assertConversionsBelongToAffiliate(array $conversionIds, Affiliate $affiliate): void
    {
        $conversions = AffiliateConversion::query()
            ->forOwner()
            ->whereIn('id', $conversionIds)
            ->get();

        if ($conversions->count() !== count($conversionIds)) {
            throw ValidationException::withMessages([
                'data.conversion_ids' => 'One or more selected conversions are not accessible in the current owner scope.',
            ]);
        }

        foreach ($conversions as $conversion) {
            if ((string) $conversion->affiliate_id !== (string) $affiliate->getKey()) {
                throw ValidationException::withMessages([
                    'data.conversion_ids' => 'Every selected conversion must belong to the selected affiliate.',
                ]);
            }

            if (! $conversion->status->equals(ApprovedConversion::class) || $conversion->affiliate_payout_id !== null) {
                throw ValidationException::withMessages([
                    'data.conversion_ids' => 'Every selected conversion must be approved and unlinked from another payout.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, string>|null  $selectedIds
     * @return array<string, string>
     */
    public static function conversionOptions(mixed $affiliateId, ?string $search, ?array $selectedIds = null): array
    {
        if ((! is_string($affiliateId) && ! is_int($affiliateId)) || (string) $affiliateId === '') {
            return [];
        }

        $query = AffiliateConversion::query()
            ->forOwner()
            ->where('affiliate_id', (string) $affiliateId)
            ->where('status', ApprovedConversion::value())
            ->whereNull('affiliate_payout_id');

        if ($selectedIds !== null) {
            $query->whereIn('id', $selectedIds);
        } elseif (is_string($search) && mb_trim($search) !== '') {
            $pattern = LikeSearch::contains($search);
            $query->where(function ($nested) use ($pattern): void {
                LikeSearch::whereLike($nested, 'external_reference', $pattern);
                LikeSearch::orWhereLike($nested, 'source_ref', $pattern);
            });
        }

        $conversions = $query
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get();

        $options = [];

        foreach ($conversions as $conversion) {
            $reference = $conversion->external_reference ?: $conversion->source_ref ?: (string) $conversion->getKey();
            $currency = mb_strtoupper((string) $conversion->commission_currency);
            $amount = MoneyFormatter::decimalFromMinor((int) $conversion->commission_minor, $currency);
            $options[(string) $conversion->getKey()] = sprintf('%s — %s %s', $reference, $amount, $currency);
        }

        return $options;
    }

    public static function affiliateOptions(string $search): array
    {
        $query = Affiliate::query()->whereCommercialPurpose();

        if ((bool) config('affiliates.owner.enabled', false)) {
            $query->forOwner();
        }

        $pattern = LikeSearch::contains($search);

        return $query
            ->where(function ($nested) use ($pattern): void {
                LikeSearch::whereLike($nested, 'name', $pattern);
                LikeSearch::orWhereLike($nested, 'code', $pattern);
            })
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->toArray();
    }
}
