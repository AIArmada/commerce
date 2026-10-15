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

## Update a program

`UpdateReferralProgram` changes a program's mutable attributes under exactly the creation rules. It re-resolves the program through `ReferralProgramGuard` with a row lock inside one transaction, so an owner mismatch or a concurrent write is caught before anything is stored.

```php
<?php

use AIArmada\Referrals\Actions\UpdateReferralProgram;

UpdateReferralProgram::run($program, [
    'name' => 'Member referral v2',
    'invite_expiry_days' => 21,
    'ends_at' => '2027-01-01T00:00:00Z',
]);
```

Attributes you omit keep their stored values, so a partial edit never resets the currency or drops a cap. A name-only edit re-derives the slug exactly as creation would; supply `slug` explicitly to control it, or leave the name untouched to keep the stored slug.

`status` and the owner tuple are immutable here: a payload that names either is rejected, and lifecycle changes go through your own status actions. A rejected attribute raises `ReferralProgramAttributeException`, an `InvalidArgumentException` that also carries the offending attribute name (presentation layers use it to attach the error to the right form field); the stored program is left unchanged.

Editing a program never rewrites a promise already made. A qualified invite keeps the frozen specification copied onto its reward rows at qualification time, so changing a program's rewards affects only invites qualified after the change.

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
    'family_key' => null,
]
```

Cash and voucher amounts use integer minor units and an explicit currency. Points use the integer `points` field. Monetary values must not be represented as floats.

The `reward_points` driver may carry a `family_key` slug (1..64, `[a-z0-9_-]`) that binds that side's promise to the Rewards program family allowed to fulfil it. Because one Rewards program's points component has one value, two sides with different points amounts must each name their own family; creation fails when the sides share a family (or omit it) with differing amounts.

## Fulfilment rails

### Manual

The default rail requires a non-empty external reference in reward metadata. Set the metadata key configured at `referrals.fulfillment.drivers.manual.reference_metadata_key` before calling `FulfillReferralReward`. A missing reference marks the reward `Failed`; correct the metadata and retry.

The manual rail records that reference in referrals. The host must perform and verify the actual compensation or reversal itself before calling the matching action; manual reversal records the reason and reference but does not contact an external system.

### Vouchers

Install `aiarmada/vouchers`, enable the voucher driver, and put the input fields accepted by the vouchers package's `CreateVoucher` action in the `voucher` array. The adapter sets a deterministic code, currency, and idempotency marker. It stores the issued code as `external_reference`. Reversal uses vouchers' `ExpireVoucher` action, which expires the code rather than deleting the voucher.

Every referral-issued voucher is **single-use and beneficiary-bound**: the adapter forces `usage_limit = 1` and `usage_limit_per_user = 1` (both alias spellings) and writes `metadata.assignee = ['type' => frozen beneficiary type, 'id' => frozen beneficiary id]`, the marker the vouchers package enforces at validation and at usage recording. The voucher is worth exactly the frozen `amount_minor`. A spec that asks for a different usage limit, a `value` that differs from `amount_minor`, or its own `metadata.assignee` is rejected at program creation; a legacy frozen spec is normalized to 1/1 at issuance. A replay returns the same code only when the existing voucher is single-use and bound to the frozen beneficiary — an unlimited or unbound voucher fails closed.

### Affiliates cash

Install `aiarmada/affiliates`, enable the `affiliate_cash` driver, and configure an amount and currency. Bind `ReferralAffiliateResolver` to map a frozen beneficiary to an `Affiliate` recipient. The adapter calls `RecordAffiliateAward` using an idempotency key derived from invite and side; `Replayed` is successful, while `Rejected` and `NoAffiliate` fail the referral reward. Reversal calls `ReverseAffiliateConversion` on the stored conversion ID.

Bind `ReferralAffiliateAwardPolicy` as the host's `AffiliateAwardPolicy` to authorize only matching pending referral rewards. Affiliates accepts one `AffiliateAwardPolicy` binding for all award callers. A host that also records non-referral awards must bind a prefix-dispatching composite that delegates `referrals:award:` keys to this policy and other keys to the host's other policy. The referral policy uses exact amount limits and rejects mismatched amount, currency, source, qualification key, or beneficiary mapping.

### Rewards points

Install `aiarmada/rewards` and enable `reward_points`. The adapter dispatches `EvaluateRewardEntitlementJob` synchronously with `referrals:qualified:{inviteId}:{side}` only after the host `RewardSourceFactReader` returns a valid fact for that key. Synchronous dispatch keeps the current owner context available to Rewards' owner-scoped program queries. That scope also applies to global Rewards programs: the packaged default `rewards.owner.include_global=false` excludes platform programs from a tenant referral's dispatch, so the side remains `Failed` without an award. Set `rewards.owner.include_global=true` when tenant referrals should fulfil against global Rewards programs. Configure a Rewards program with the `referral_qualified` outcome and teach the host reader to map that key to the correct actor and sharer: the referee is the actor and the referrer is the sharer. The fact must confirm claimed identity, attribution, validity, and the frozen beneficiary.

The dispatch is scoped to exactly the frozen side: the referrer is evaluated as the `sharer` role, the referee as the `participant` role, both bound to the reward's frozen beneficiary id and type, and — when the spec names one — the spec's `family_key`. Only that (role, recipient, family) tuple is evaluated, so each side's fulfilment creates exactly one award instead of fanning out across the whole fact's sides. The frozen beneficiary type must simply be listed in `rewards.recipient_types`; the job receives it explicitly and no longer reads the first entry.

The Rewards job derives points from its active program rules; it does not accept an amount from referrals. Configure that rule to award exactly the frozen `points` value on the referral reward, and keep the host's source fact and Rewards rules aligned with the referral promise.

Points are posted by Rewards through its entitlement and ledger flow; referrals never calls a direct points-credit API. Before marking the referral reward fulfilled, the adapter verifies exactly one live (not reversed, not expired) Rewards award for the same business key, beneficiary, role, points component, and family; the dispatch and the verification share one nested transaction, so a missing, ambiguous, or mis-sized award leaves the reward `Failed` with zero RewardAward rows for the side key, and a retry after host rule alignment re-evaluates from a clean slate. An exposed points amount must equal the frozen referral spec exactly.

Reversal is final for that referral reward: a reversed side cannot be fulfilled again, and the invite cannot re-complete through that reward. Reversal is also **driven and verified**, not delegated to the host: the adapter probes the side's live (held, pending-review, approved, or fulfilled) points award, and when one exists it runs the Rewards corrections sweep with `referrals:qualified:{inviteId}:{side}` as an explicit tainted key. The sweep rejects held and pending-review awards, reverses approved work, and routes fulfilled awards through the recovery-aware connector under each program's own owner. The referral reaches `Reversed` only when no live award remains for the side; the metadata records `rewards_reversal = verified_corrections_sweep` and `rewards_reversal_business_key`. A blocked reversal — an unattested active reservation the default null resolver cannot attest, a per-award sweep error, or any throw — fails the reversal, so the referral stays `Fulfilled` and retryable instead of terminal `Reversed` with spendable points.

## First-registration example

For an ilmu360-shaped onboarding flow, create a program with `qualifier_outcome` set to `first_registration`. When the addressed invitee completes their first registration and the host has verified the registration identity, accept the invite and call:

```php
QualifyReferral::run($invite, 'registration:' . $user->getKey(), $user->created_at->toImmutable());
```

The host defines what counts as the first valid registration and ensures it reports only once. The package enforces the invitee identity and business-key replay rules.

## Expiry, revocation, and reversal

Schedule `php artisan referrals:expire-invites` to transition due pending or accepted invites to `Expired`. `RevokeReferralInvite` is limited to pending and accepted invites. `ReverseReferralReward` delegates to the rail, stores the reversal result, clears `rewarded_at` when returning a rewarded invite to `Qualified`, and does not make the reversed reward available for fulfilment again.
