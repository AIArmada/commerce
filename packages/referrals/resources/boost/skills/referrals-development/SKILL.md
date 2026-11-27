---
name: referrals-development
description: Integrate and develop referrals using Laravel Dune package boundaries and owner-scoped actions.
---

# Referrals development

- Resolve tenant writes through the owning ReferralProgram using ReferralProgramGuard. Invite and reward IDs alone do not authorize access.
- CreateReferralProgram and UpdateReferralProgram validate program attributes. Use TransitionReferralProgramStatus for locked lifecycle changes.
- CreateReferralInvite returns an action result containing the invite and a one-time plaintext token; do not log or persist the token. AcceptReferralInvite verifies the current token under lock.
- QualifyReferral freezes the reward intent. FulfillReferralReward and ReverseReferralReward own reward state transitions and metadata writes.
- Implement ReferralRewardFulfillment and ReferralAffiliateResolver with immutable AIArmada\Referrals\Data\ReferralRewardContext. Return ReferralFulfillmentResult; never mutate referral rows in a driver.
- Affiliates, Rewards, and Vouchers own their respective settlement ledgers. Use deterministic keys and preserve the host's owner context. A failed adapter must not leave partial database writes.
- Run referrals:expire-invites for bounded owner-scoped expiration.
