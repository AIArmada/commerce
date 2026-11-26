<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Actions;

use AIArmada\Affiliates\Features\Commissions\Api\VolumeTierAdministration;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Features\Programs\Api\ProgramReferenceReader;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

final class AdministerVolumeTier implements VolumeTierAdministration
{
    public function __construct(private readonly ProgramReferenceReader $programs) {}

    public function create(array $data): string
    {
        return (new AffiliateVolumeTier)->getConnection()->transaction(function () use ($data): string {
            $attributes = $this->validated($data);
            $this->lockProgram($attributes['program_id'] ?? null);

            return (string) AffiliateVolumeTier::query()->create($attributes)->getKey();
        }, attempts: 3);
    }

    public function update(string $tierId, array $data): void
    {
        (new AffiliateVolumeTier)->getConnection()->transaction(function () use ($tierId, $data): void {
            $tier = $this->lockTier($tierId);
            $attributes = $this->validated(array_replace($tier->only([
                'program_id', 'name', 'min_volume_minor', 'max_volume_minor', 'commission_rate_basis_points', 'currency', 'period',
            ]), $data));

            if (($attributes['program_id'] ?? null) !== $tier->program_id) {
                $this->lockProgram($attributes['program_id'] ?? null);
            }

            $tier->update($attributes);
        }, attempts: 3);
    }

    public function delete(string $tierId): bool
    {
        return (new AffiliateVolumeTier)->getConnection()->transaction(
            fn (): bool => (bool) $this->lockTier($tierId)->delete(),
            attempts: 3,
        );
    }

    private function lockTier(string $id): AffiliateVolumeTier
    {
        $tier = $this->query()->findOrFail($id);
        $this->lockProgram($tier->program_id);

        return $this->query()->where('program_id', $tier->program_id)->lockForUpdate()->findOrFail($id);
    }

    /** @return Builder<AffiliateVolumeTier> */
    private function query(): Builder
    {
        $query = AffiliateVolumeTier::query();

        if ((bool) config('affiliates.owner.enabled', false) && OwnerContext::isExplicitGlobal()) {
            return $query->withoutGlobalScope('program_owner')->where(static function (Builder $query): void {
                $query->whereNull('program_id')->orWhereIn('program_id', AffiliateProgram::query()->select('id'));
            });
        }

        return $query;
    }

    private function lockProgram(?string $programId): void
    {
        if ($programId === null) {
            if ((bool) config('affiliates.owner.enabled', false) && ! OwnerContext::isExplicitGlobal()) {
                throw new AuthorizationException('Global volume tiers require explicit global context.');
            }

            return;
        }

        $this->programs->lockForAdministration($programId, spl_object_id((new AffiliateVolumeTier)->getConnection()));
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        $attributes = Validator::make($data, [
            'program_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'min_volume_minor' => ['required', 'integer', 'min:0'],
            'max_volume_minor' => ['nullable', 'integer', 'gte:min_volume_minor'],
            'commission_rate_basis_points' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii'],
            'period' => ['required', 'string', 'in:weekly,monthly,quarterly,yearly,lifetime'],
        ])->validate();

        $attributes['currency'] = mb_strtoupper($attributes['currency']);

        return $attributes;
    }
}
