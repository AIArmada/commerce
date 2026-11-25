<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Actions\Affiliates\CreateAffiliate;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateRecord;
use AIArmada\Affiliates\Features\Identity\Api\RegistrationApi;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Queries\AffiliateLookupQuery;
use AIArmada\Affiliates\Features\Upline\Api\UplineApi;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use InvalidArgumentException;

final readonly class RegisterAffiliate implements RegistrationApi
{
    public function __construct(
        private AffiliateLookupQuery $lookup,
        private CreateAffiliate $create,
        private UplineApi $upline,
    ) {}

    public function register(array $data, RecordReference $owner, ?string $referralCode, ?string $cookie): AffiliateRecord
    {
        $ownerModel = EloquentRecordReference::resolve($owner);
        $connection = (new Affiliate)->getConnection();

        return $connection->transaction(function () use ($data, $ownerModel, $referralCode, $cookie): AffiliateRecord {
            $sponsor = null;
            if ($referralCode !== null && mb_trim($referralCode) !== '') {
                $sponsor = $this->lookup->findByCode($referralCode);
                if ($sponsor === null || ! $sponsor->canBeAttributed() || ! $sponsor->isCommercialPurpose()) {
                    throw new InvalidArgumentException('The referral code does not identify an eligible affiliate.');
                }
            } elseif ($cookie !== null && $cookie !== '') {
                $sponsor = $this->lookup->findActiveAffiliateByCookie($cookie);
                if ($sponsor !== null && ! $sponsor->isCommercialPurpose()) {
                    $sponsor = null;
                }
            }

            unset($data['parent_affiliate_id']);
            if ($sponsor !== null) {
                $data['parent_affiliate_id'] = (string) $sponsor->getKey();
            }

            $affiliate = $this->create->handle($data, $ownerModel);
            if ($sponsor !== null) {
                $this->upline->add((string) $affiliate->getKey(), (string) $sponsor->getKey());
            }

            return new AffiliateRecord(
                id: (string) $affiliate->getKey(),
                code: $affiliate->code,
                name: $affiliate->name,
                active: $affiliate->isActive(),
                canBeAttributed: $affiliate->canBeAttributed(),
                defaultVoucherCode: $affiliate->default_voucher_code,
            );
        });
    }
}
