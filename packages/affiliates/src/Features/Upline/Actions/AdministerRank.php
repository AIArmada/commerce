<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateRankMaintenance;
use AIArmada\Affiliates\Features\Upline\Api\RankAdministration;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class AdministerRank implements RankAdministration
{
    public function __construct(private AffiliateRankMaintenance $affiliates) {}

    public function create(array $data): string
    {
        return (string) AffiliateRank::query()->create($this->attributes($data, creating: true))->getKey();
    }

    public function update(string $rankId, array $data): void
    {
        (new AffiliateRank)->getConnection()->transaction(function () use ($rankId, $data): void {
            AffiliateRank::query()->forOwner()->lockForUpdate()->findOrFail($rankId)->update($this->attributes($data));
        }, attempts: 3);
    }

    public function delete(string $rankId): bool
    {
        $connection = (new AffiliateRank)->getConnection();

        if ($connection !== (new Affiliate)->getConnection()
            || $connection !== (new AffiliateRankHistory)->getConnection()) {
            throw new LogicException('Rank removal requires a shared database connection.');
        }

        return $connection->transaction(function () use ($rankId): bool {
            $rank = AffiliateRank::query()->forOwner()->lockForUpdate()->findOrFail($rankId);
            if (AffiliateRankHistory::query()->withoutGlobalScope('affiliate_owner')
                ->where(static fn ($query) => $query->where('from_rank_id', $rankId)->orWhere('to_rank_id', $rankId))
                ->exists()) {
                throw ValidationException::withMessages(['rank' => 'A rank referenced by qualification history cannot be deleted.']);
            }

            $this->affiliates->detachRank($rankId);

            if (! $rank->delete()) {
                throw new LogicException('The affiliate rank could not be deleted.');
            }

            return true;
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, bool $creating = false): array
    {
        $attributes = Arr::only($data, ['name', 'slug', 'level', 'min_personal_sales', 'min_team_sales', 'min_active_downlines', 'currency', 'commission_rate_basis_points', 'override_rates', 'benefits', 'metadata']);
        $presence = $creating ? [] : ['sometimes'];

        if (isset($attributes['currency']) && is_string($attributes['currency'])) {
            $attributes['currency'] = mb_strtoupper(mb_trim($attributes['currency']));
        }

        $validated = Validator::make($attributes, [
            'name' => [...$presence, 'required', 'string', 'max:255'],
            'slug' => [...$presence, 'required', 'string', 'max:255'],
            'level' => [...$presence, 'required', 'integer', 'min:1', 'max:2147483647'],
            'commission_rate_basis_points' => [...$presence, 'required', 'integer', 'between:0,10000'],
            'min_personal_sales' => ['sometimes', 'required', 'integer', 'between:0,2147483647'],
            'min_team_sales' => ['sometimes', 'required', 'integer', 'between:0,2147483647'],
            'min_active_downlines' => ['sometimes', 'required', 'integer', 'between:0,2147483647'],
            'currency' => ['sometimes', 'required', 'string', 'regex:/^[A-Z]{3}$/D'],
            'override_rates' => ['sometimes', 'nullable', 'array'],
            'override_rates.*' => ['required', 'integer', 'between:0,10000'],
            'benefits' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ])->validate();

        if (is_array($validated['override_rates'] ?? null)) {
            foreach ($validated['override_rates'] as $depth => $rate) {
                if (! is_int($depth) || $depth < 1) {
                    throw ValidationException::withMessages(['override_rates' => 'Override depths must be positive integers.']);
                }

                $validated['override_rates'][$depth] = (int) $rate;
            }
        }

        return $validated;
    }
}
