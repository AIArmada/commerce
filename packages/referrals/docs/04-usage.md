---
title: Referrals Usage
---

# Referrals Usage

## Create, accept, qualify, and fulfil

The host owns identity and decides when a meaningful action has happened. The following example uses host-owned `Tenant` and `User` models; replace them with your application's models. `referrerContact` is optional, but passing the referrer's verified contact lets the package reject self-invites.

```php
<?php

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Referrals\Actions\AcceptReferralInvite;
use AIArmada\Referrals\Actions\CreateReferralInvite;
use AIArmada\Referrals\Actions\CreateReferralProgram;
use AIArmada\Referrals\Actions\FulfillReferralReward;
use AIArmada\Referrals\Actions\QualifyReferral;
use App\Models\Tenant;
use App\Models\User;

$tenant = Tenant::query()->findOrFail($tenantId);
$referrer = User::query()->findOrFail($referrerId);
$invitee = User::query()->findOrFail($newUserId);

OwnerContext::withOwner($tenant, function () use ($referrer, $invitee): void {
    $program = CreateReferralProgram::run([
        'name' => 'Member referral',
        'slug' => 'member-referral',
        'status' => 'active',
        'qualifier_outcome' => 'first_registration',
        'currency' => 'MYR',
        'max_invites_per_referrer' => 10,
        'max_qualified_per_referrer_per_month' => 5,
        'referrer_reward' => ['driver' => 'manual'],
        'referee_reward' => ['driver' => 'manual'],
    ]);

    $created = CreateReferralInvite::run(
        $program,
        $referrer,
        'new.member@example.com',
        $referrer->email,
    );

    // Deliver $created->token once. The database stores only its SHA-256 hash.
    $accepted = AcceptReferralInvite::run(
        $created->token,
        $invitee,
        'new.member@example.com',
    );

    // Call this only after the host confirms the meaningful outcome.
    $qualified = QualifyReferral::run($accepted, 'registration:' . $invitee->getKey());

    foreach ($qualified->rewards as $reward) {
        // A host records its real external action reference for the manual rail.
        $reward->metadata = ['external_reference' => 'host-receipt-' . $reward->id];
        $reward->save();

        FulfillReferralReward::run($reward);
    }
});
```

Use a stable, unique host business key, such as a registration or order identifier. Replaying qualification returns the stored invite and rewards and never creates a second promise. `occurredAt` can be supplied as a `CarbonImmutable` when the host needs to report the original event time.

## Invite claims and privacy

Contacts are trimmed; email addresses are lowercased and phone numbers are normalized to digits with an optional leading `+`. The normalized contact is SHA-256 hashed for duplicate claims and encrypted at rest for delivery workflows. The plaintext invite token is returned only in `ReferralInviteCreationResult`; never log or persist it in your own invite table.

The host should pass a referrer's verified contact to `CreateReferralInvite` to enable self-invite rejection. Invitee acceptance must use the same contact that was addressed. Tokens and IDs are re-resolved within the current owner context on every action.

Reissuing a live pending invite for the same referrer rotates its token and returns the replacement once; the prior token becomes invalid. An accepted invite remains claimed and is returned without a token.

## Reward specification

The two side specs are independent and are frozen when qualification succeeds. Use `null` or an empty array to skip a side. Supported fields are:

```php
[
    'driver' => 'manual|voucher|affiliate_cash|reward_points',
    'amount_minor' => 2500,
    'points' => null,
    'currency' => 'MYR',
    'voucher' => null,
]
```

Cash and voucher amounts use integer minor units and an explicit currency. Points use the integer `points` field. Monetary values must not be represented as floats.

## Fulfilment rails

### Manual

The default rail requires a non-empty external reference in reward metadata. Set the metadata key configured at `referrals.fulfillment.drivers.manual.reference_metadata_key` before calling `FulfillReferralReward`. A missing reference marks the reward `Failed`; correct the metadata and retry.

The manual rail records that reference in referrals. The host must perform and verify the actual compensation or reversal itself before calling the matching action; manual reversal records the reason and reference but does not contact an external system.

### Vouchers

Install `aiarmada/vouchers`, enable the voucher driver, and put the input fields accepted by the vouchers package's `CreateVoucher` action in the `voucher` array. The adapter sets a deterministic code, currency, and idempotency marker. It stores the issued code as `external_reference`. Reversal uses vouchers' `ExpireVoucher` action, which expires the code rather than deleting the voucher.

### Affiliates cash

Install `aiarmada/affiliates`, enable the `affiliate_cash` driver, and configure an amount and currency. Bind `ReferralAffiliateResolver` to map a frozen beneficiary to an `Affiliate` recipient. The adapter calls `RecordAffiliateAward` using an idempotency key derived from invite and side; `Replayed` is successful, while `Rejected` and `NoAffiliate` fail the referral reward. Reversal calls `ReverseAffiliateConversion` on the stored conversion ID.

Bind `ReferralAffiliateAwardPolicy` as the host's `AffiliateAwardPolicy` to authorize only matching pending referral rewards. Affiliates accepts one `AffiliateAwardPolicy` binding for all award callers. A host that also records non-referral awards must bind a prefix-dispatching composite that delegates `referrals:award:` keys to this policy and other keys to the host's other policy. The referral policy uses exact amount limits and rejects mismatched amount, currency, source, qualification key, or beneficiary mapping.

### Rewards points

Install `aiarmada/rewards` and enable `reward_points`. The adapter dispatches `EvaluateRewardEntitlementJob` synchronously with `referrals:qualified:{inviteId}:{side}` only after the host `RewardSourceFactReader` returns a valid fact for that key. Synchronous dispatch keeps the current owner context available to Rewards' owner-scoped program queries. Configure a Rewards program with the `referral_qualified` outcome and teach the host reader to map that key to the correct actor and sharer: the referee is the actor and the referrer is the sharer. The fact must confirm claimed identity, attribution, validity, and the frozen beneficiary.

The Rewards job derives points from its active program rules; it does not accept an amount from referrals. Configure that rule to award exactly the frozen `points` value on the referral reward, and keep the host's source fact and Rewards rules aligned with the referral promise.

The job uses the first entry in `rewards.recipient_types`; that value must match the beneficiary morph type stored on the referral reward.

Points are posted by Rewards through its entitlement and ledger flow; referrals never calls a direct points-credit API. Before marking the referral reward fulfilled, the adapter verifies a non-refused Rewards award for the same business key, beneficiary, and points component. A missing award fails the referral reward, and an exposed points amount must match the frozen referral spec.

Reversal is final for that referral reward: a reversed side cannot be fulfilled again, and the invite cannot re-complete through that reward. The referral reward metadata records `rewards_reversal = pending_host_corrections_sweep` and `rewards_reversal_business_key` until the host's Rewards corrections sweep uses that key to issue and settle any required correction.

## First-registration example

For an ilmu360-shaped onboarding flow, create a program with `qualifier_outcome` set to `first_registration`. When the addressed invitee completes their first registration and the host has verified the registration identity, accept the invite and call:

```php
QualifyReferral::run($invite, 'registration:' . $user->getKey(), $user->created_at->toImmutable());
```

The host defines what counts as the first valid registration and ensures it reports only once. The package enforces the invitee identity and business-key replay rules.

## Expiry, revocation, and reversal

Schedule `php artisan referrals:expire-invites` to transition due pending or accepted invites to `Expired`. `RevokeReferralInvite` is limited to pending and accepted invites. `ReverseReferralReward` delegates to the rail, stores the reversal result, clears `rewarded_at` when returning a rewarded invite to `Qualified`, and does not make the reversed reward available for fulfilment again.
