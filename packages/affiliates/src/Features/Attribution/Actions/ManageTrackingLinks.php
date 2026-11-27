<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Actions;

use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkData;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\Affiliates\Features\Conversions\Api\LinkConversionReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Links\Actions\DeleteLink;
use AIArmada\Links\LinksServiceProvider;
use AIArmada\Links\Models\Link;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ManageTrackingLinks implements TrackingLinkAdministration
{
    public function __construct(
        private CreateTrackingLink $creator,
        private UpdateTrackingLink $updater,
        private LinkConversionReader $conversions,
    ) {}

    public function create(string $affiliateId, string $destinationUrl, array $attributes = []): TrackingLinkData
    {
        return $this->data($this->creator->handle($affiliateId, $destinationUrl, $attributes));
    }

    public function update(string $linkId, array $attributes): TrackingLinkData
    {
        return $this->data($this->updater->handle($linkId, $attributes));
    }

    public function delete(string $linkId): bool
    {
        $link = AffiliateLink::query()->findOrFail($linkId);
        $connection = $link->getConnection();
        $this->assertSharedConnection($link);

        return $connection->transaction(function () use ($linkId, $link): bool {
            app(AffiliateAccessGuard::class)->lockForWrite($link->affiliate_id, spl_object_id($link->getConnection()));
            $locked = AffiliateLink::query()->where('affiliate_id', $link->affiliate_id)->lockForUpdate()->findOrFail($linkId);

            return $this->remove($locked);
        }, attempts: 3);
    }

    public function deleteForAffiliate(string $affiliateId): void
    {
        $model = new AffiliateLink;
        $this->assertSharedConnection($model);

        $model->getConnection()->transaction(function () use ($affiliateId): void {
            app(AffiliateAccessGuard::class)->lockForWrite($affiliateId, spl_object_id((new AffiliateLink)->getConnection()));

            AffiliateLink::query()->where('affiliate_id', $affiliateId)->lockForUpdate()->chunkById(100, function ($links): void {
                foreach ($links as $link) {
                    $this->remove($link);
                }
            });
        }, attempts: 3);
    }

    private function remove(AffiliateLink $link): bool
    {
        if ($this->conversions->hasLinkHistory((string) $link->getKey(), spl_object_id($link->getConnection()))) {
            throw ValidationException::withMessages(['link' => 'A link with recorded conversions cannot be deleted.']);
        }

        if (class_exists(Link::class) && app()->providerIsLoaded(LinksServiceProvider::class)) {
            foreach ($link->trackedLink()->get() as $tracked) {
                if (! app(DeleteLink::class)->handle($tracked)) {
                    throw new LogicException('The associated public link could not be deleted.');
                }
            }
        }

        if (! $link->delete()) {
            throw new LogicException('The affiliate link could not be deleted.');
        }

        return true;
    }

    private function assertSharedConnection(AffiliateLink $link): void
    {
        $connection = $link->getConnection();

        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($connection));

        if (class_exists(Link::class) && app()->providerIsLoaded(LinksServiceProvider::class) && $connection !== (new Link)->getConnection()) {
            throw new LogicException('Tracking link removal requires a shared database connection.');
        }
    }

    public function setEnabled(string $linkId, bool $enabled): void
    {
        $reference = AffiliateLink::query()->findOrFail($linkId);
        $connection = $reference->getConnection();
        $this->assertSharedConnection($reference);

        $connection->transaction(function () use ($reference, $linkId, $enabled): void {
            app(AffiliateAccessGuard::class)->lockForWrite($reference->affiliate_id, spl_object_id($reference->getConnection()));
            $link = AffiliateLink::query()->where('affiliate_id', $reference->affiliate_id)->lockForUpdate()->findOrFail($linkId);
            if ($link->isEnabled() !== $enabled) {
                $link->update(['deactivated_at' => $enabled ? null : CarbonImmutable::now()]);
            }
        }, attempts: 3);
    }

    public function recordClick(string $linkId): void
    {
        AffiliateLink::query()->whereKey($linkId)->increment('clicks');
    }

    public function recordConversion(string $linkId): void
    {
        AffiliateLink::query()->whereKey($linkId)->increment('conversions');
    }

    private function data(AffiliateLink $link): TrackingLinkData
    {
        return new TrackingLinkData((string) $link->getKey(), $link->tracking_url, $link->subject_type, $link->subject_key);
    }
}
