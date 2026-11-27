@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
@verbatim
# Filament Referrals development

- This package is a Filament adapter. Core Referrals owns program, invite and reward mutations.
- Use TransitionReferralProgramStatus, RevokeReferralInvite, FulfillReferralReward and ReverseReferralReward for lifecycle actions. Do not persist lifecycle state directly from a resource or table.
- Keep query scoping in Persistence/ReferralFilamentScope. Visible records are not automatically writable: the core guard must re-resolve each record under its program owner.
- Authorize custom actions on the server, not only through visibility callbacks. Never expose plaintext invite contacts or tokens.
@endverbatim
@endif
