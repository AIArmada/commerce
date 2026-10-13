---
title: Referrals Troubleshooting
---

# Referrals Troubleshooting

## Fulfilment reports an unavailable driver

Check `referrals.fulfillment.drivers.<driver>.enabled`, install the optional package for that rail, and confirm its public classes can be autoloaded. Unknown, disabled, and unavailable drivers fail closed so a promised reward remains visible for repair.

## Manual fulfilment fails without a reference

Set a non-empty reward metadata value using the configured `reference_metadata_key`, then retry the `Failed` reward. Manual reversal also requires the stored external reference.

## The same contact cannot create another invite

Only one live invite per program and normalized contact is claimable. A repeat by the original referrer rotates the token while the invite is pending and returns the replacement plaintext once; after acceptance it returns the existing invite with no token. A different referrer receives `ReferralAlreadyClaimedException`. Expire or revoke an unqualified invite before creating a replacement.

## Acceptance says the token or contact is invalid

The token is stored as a one-way SHA-256 hash and is returned only once. Check that the caller retained the original token, that the invite is still pending and before its expiry instant, and that the supplied contact normalizes to the addressed contact.

## Owner-scoped invite or reward cannot be found

Enter the owner context that created the program with `OwnerContext::withOwner($owner, ...)`. A missing owner is not global access. Use `OwnerContext::withOwner(null, ...)` only for an intentional global operation. Invite and reward actions revalidate their parent program under the current context.

## Affiliate award is refused

Check that a host resolver returns the matching Affiliate for the frozen beneficiary and that the single bound `AffiliateAwardPolicy` dispatches `referrals:award:` keys to `ReferralAffiliateAwardPolicy`. The policy rejects pending-state, amount, currency, source, qualification-key, or recipient mismatches.

## Rewards points job is not dispatched

The host `RewardSourceFactReader` must recognize `referrals:qualified:{inviteId}:{side}`, return outcome `referral_qualified`, mark identity/attribution/validity checks true, and map the actor or sharer to the frozen beneficiary. Points still flow through Rewards; referral code never credits a balance directly.

## Rewards points fulfilment fails after evaluation

The synchronous Rewards job must leave a non-refused points award for the referral business key and frozen beneficiary. Check enrollment, programme eligibility, caps, and the frozen point amount; a refused evaluation or missing award remains `Failed` in referrals.
