<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateRecord;
use AIArmada\Affiliates\Features\Identity\Api\RegistrationApi;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Identity\Queries\AffiliateLookupQuery;
use AIArmada\Affiliates\Features\Upline\Api\UplineApi;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class RegisterAffiliate implements RegistrationApi
{
    public function __construct(
        private AffiliateLookupQuery $lookup,
        private CreateAffiliate $create,
        private UplineApi $upline,
    ) {}

    public function register(array $data, RecordReference $owner, ?string $referralCode, ?string $cookie, ?RecordReference $actor = null): AffiliateRecord
    {
        if (! config('affiliates.registration.enabled', true)) {
            throw new InvalidArgumentException('Affiliate registration is disabled.');
        }

        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'handle' => ['nullable', 'string', 'max:40'],
            'code' => ['nullable', 'string', 'alpha_dash:ascii', 'max:64'],
            'description' => ['nullable', 'string'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:32'],
        ])->validate();

        $code = $data['code'] ?? null;

        if (is_string($code) && $code !== '' && ! $this->lookup->codeAvailable($code)) {
            throw ValidationException::withMessages(['code' => 'The affiliate code has already been taken.']);
        }

        $ownerModel = EloquentRecordReference::resolve($owner);
        $actorModel = $actor === null ? null : EloquentRecordReference::resolve($actor);
        $connection = (new Affiliate)->getConnection();

        try {
            return $connection->transaction(function () use ($data, $ownerModel, $referralCode, $cookie, $actorModel): AffiliateRecord {
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

                if ($sponsor !== null) {
                    $sponsor = Affiliate::query()->forOwner()->whereKey($sponsor->getKey())->lockForUpdate()->first();

                    if ($sponsor === null || ! $sponsor->canBeAttributed() || ! $sponsor->isCommercialPurpose()) {
                        if ($referralCode !== null && mb_trim($referralCode) !== '') {
                            throw new InvalidArgumentException('The referral code does not identify an eligible affiliate.');
                        }

                        $sponsor = null;
                    }
                }

                if ($sponsor !== null) {
                    $data['parent_affiliate_id'] = (string) $sponsor->getKey();
                }

                $affiliate = $this->create->handle($data, $ownerModel, $actorModel);
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
        } catch (UniqueConstraintViolationException $exception) {
            if (is_string($code) && $code !== '' && ! $this->lookup->codeAvailable($code)) {
                throw ValidationException::withMessages(['code' => 'The affiliate code has already been taken.']);
            }

            throw $exception;
        }
    }
}
