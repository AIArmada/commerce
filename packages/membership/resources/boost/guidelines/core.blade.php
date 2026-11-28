# Membership

Membership owns applications, invitations and membership-role reconciliation. Call `AIArmada\Membership\Actions\AddMemberAction::run()`, `RemoveMemberAction::run()`, `ChangeMemberRoleAction::run()` and `SyncMembershipRolesAction` through the container. The subject must expose a belongs-to-many members relationship; use the `HasMembers` trait. Resolve owner scope before mutation, serialize changes under the subject row lock, and preserve the host subject's membership invariants. Invitation delivery, application lifecycle events and notifications, and `MembershipHook` callbacks occur after the outermost transaction commits. Use `MembershipMutationGuard` for synchronous invariant checks; hooks observe committed mutations and must not be used to veto writes. Do not add compatibility aliases for moved classes.

Register invitation transport and verifier implementations through `AIArmada\Membership\Support\InvitationChannelManager`; built-in email adapters live under `AIArmada\Membership\Drivers`.

The `membership:sync-roles` command is registered from `AIArmada\Membership\EntryPoints\Commands\SyncRolesCommand` and delegates role reconciliation to `SyncMembershipRolesAction`.
