<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Contracts\CreativeAssetRemoval;
use AIArmada\Affiliates\Features\Programs\Api\CreativeAdministration;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final readonly class AdministerCreative implements CreativeAdministration
{
    public function __construct(private CreativeAssetRemoval $assets) {}

    public function create(?string $programId, array $data): string
    {
        return (new AffiliateProgramCreative)->getConnection()->transaction(function () use ($programId, $data): string {
            $this->lockProgram($programId);

            return (string) AffiliateProgramCreative::query()->create([
                ...$this->validated($data),
                'program_id' => $programId,
            ])->getKey();
        }, attempts: 3);
    }

    public function update(?string $programId, string $creativeId, array $data): void
    {
        (new AffiliateProgramCreative)->getConnection()->transaction(function () use ($programId, $creativeId, $data): void {
            $creative = $this->lockCreative($programId, $creativeId);
            $creative->update($this->validated(array_replace($creative->only([
                'name', 'type', 'description', 'width', 'height', 'destination_url', 'tracking_code', 'metadata',
            ]), $data)));
        }, attempts: 3);
    }

    public function delete(?string $programId, string $creativeId): bool
    {
        return (new AffiliateProgramCreative)->getConnection()->transaction(function () use ($programId, $creativeId): bool {
            $creative = $this->lockCreative($programId, $creativeId);
            $this->assets->remove($creativeId, $creative->getMorphClass(), spl_object_id($creative->getConnection()));

            if (! $creative->deletePreservingMedia()) {
                throw new RuntimeException('Creative deletion failed.');
            }

            return true;
        });
    }

    private function lockCreative(?string $programId, string $creativeId): AffiliateProgramCreative
    {
        $this->lockProgram($programId);

        $query = AffiliateProgramCreative::query();

        if ($programId === null) {
            $query->withoutGlobalScope('program_owner');
        }

        return $query->where('program_id', $programId)->lockForUpdate()->findOrFail($creativeId);
    }

    private function lockProgram(?string $programId): void
    {
        if ($programId === null) {
            if ((bool) config('affiliates.owner.enabled', false) && ! OwnerContext::isExplicitGlobal()) {
                throw new AuthorizationException('General creative administration requires explicit global context.');
            }

            return;
        }

        if ((new AffiliateProgram)->getConnection() !== (new AffiliateProgramCreative)->getConnection()) {
            throw new RuntimeException('Creative administration requires a shared database connection.');
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId);
        }

        AffiliateProgram::query()->lockForUpdate()->findOrFail($programId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:banner,text_link,image,video,document,email,pdf'],
            'description' => ['nullable', 'string'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'destination_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'tracking_code' => ['required', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ])->validate();
    }
}
