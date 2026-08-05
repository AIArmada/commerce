---
title: Organizations Troubleshooting
---

## Missing context

Apply `CurrentOrganizationMiddleware` with the `true` parameter to routes that
must have a tenant. A missing or unauthorized organization resolves to no
context and raises `NoCurrentOwnerException` when context is required.

## Ownership errors

Do not update the membership pivot directly. Use `AddMemberAction`,
`ChangeMemberRoleAction`, `RemoveMemberAction`, and
`TransferOrganizationOwnershipAction`; direct pivot writes bypass application
invariants.

## Public leakage

Use `Organization::public()` for public discovery and keep application
serializers limited to explicitly public fields. Membership and workspace
payloads must be authorization-gated.
