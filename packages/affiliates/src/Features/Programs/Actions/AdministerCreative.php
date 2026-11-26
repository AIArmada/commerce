<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Contracts\CreativeAssetRemoval;
use AIArmada\Affiliates\Contracts\CreativeAssetReplacement;
use AIArmada\Affiliates\Data\PreparedCreativeAsset;
use AIArmada\Affiliates\Features\Programs\Api\CreativeAdministration;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use finfo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class AdministerCreative implements CreativeAdministration
{
    public function __construct(private CreativeAssetRemoval $assets, private CreativeAssetReplacement $replacement) {}

    public function create(?string $programId, array $data, ?string $assetBytes = null): string
    {
        $attributes = $this->validated($data);
        $asset = $this->prepareAsset($assetBytes);

        return (new AffiliateProgramCreative)->getConnection()->transaction(function () use ($programId, $attributes, $asset): string {
            $this->lockProgram($programId);

            $creative = AffiliateProgramCreative::query()->create([
                ...$attributes,
                'program_id' => $programId,
            ]);

            if ($asset !== null) {
                $this->replaceAsset($creative, $asset);
            }

            return (string) $creative->getKey();
        }, attempts: 3);
    }

    public function update(?string $programId, string $creativeId, array $data, ?string $assetBytes = null, bool $removeAsset = false): void
    {
        if ($assetBytes !== null && $removeAsset) {
            throw ValidationException::withMessages(['asset' => 'Choose either a replacement or removal.']);
        }

        $attributes = $this->validatedPatch($data);
        $asset = $this->prepareAsset($assetBytes);

        (new AffiliateProgramCreative)->getConnection()->transaction(function () use ($programId, $creativeId, $attributes, $asset, $removeAsset): void {
            $creative = $this->lockCreative($programId, $creativeId);
            $creative->update(array_replace($creative->only([
                'name', 'type', 'description', 'width', 'height', 'destination_url', 'tracking_code', 'metadata',
            ]), $attributes));

            if ($asset !== null) {
                $this->replaceAsset($creative, $asset);
            } elseif ($removeAsset) {
                $this->assets->remove($creativeId, $creative->getMorphClass(), spl_object_id($creative->getConnection()), 'creative_asset');
            }
        }, attempts: 3);
    }

    public function delete(?string $programId, string $creativeId): bool
    {
        return (new AffiliateProgramCreative)->getConnection()->transaction(function () use ($programId, $creativeId): bool {
            $creative = $this->lockCreative($programId, $creativeId);
            $this->assets->remove($creativeId, $creative->getMorphClass(), spl_object_id($creative->getConnection()), null);

            if (! $creative->deletePreservingMedia()) {
                throw new RuntimeException('Creative deletion failed.');
            }

            return true;
        }, attempts: 3);
    }

    private function replaceAsset(AffiliateProgramCreative $creative, PreparedCreativeAsset $asset): void
    {
        $this->replacement->replace((string) $creative->getKey(), $creative->getMorphClass(), spl_object_id($creative->getConnection()), $asset);
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

        AffiliateProgram::query()->sharedLock()->findOrFail($programId);
    }

    private function prepareAsset(?string $bytes): ?PreparedCreativeAsset
    {
        if ($bytes === null) {
            return null;
        }

        if ($bytes === '' || mb_strlen($bytes, '8bit') > (int) config('media-library.max_file_size', 10485760)) {
            throw ValidationException::withMessages(['asset' => 'The asset is empty or exceeds the configured upload limit.']);
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            default => throw ValidationException::withMessages(['asset' => 'The asset type is not supported.']),
        };

        return new PreparedCreativeAsset($bytes, $extension);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        return Validator::make($data, $this->rules())->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedPatch(array $data): array
    {
        $rules = array_intersect_key($this->rules(), $data);

        foreach ($rules as $field => $fieldRules) {
            $rules[$field] = ['sometimes', ...$fieldRules];
        }

        return Validator::make($data, $rules)->validate();
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:banner,text_link,image,video,document,email,pdf'],
            'description' => ['nullable', 'string'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'destination_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'tracking_code' => ['required', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
