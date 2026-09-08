---
title: Organizations Troubleshooting
---

## Missing context

Apply `CurrentOrganizationMiddleware` with the `true` parameter to routes that
must have a tenant. Configure a non-null `CurrentOrganizationResolver`; an
unconfigured `NullCurrentOrganizationResolver` fails at resolution with a
diagnostic `LogicException` when context is required. Once an application
resolver is configured, a missing or unauthorized organization raises
`NoCurrentOwnerException`.

## Ownership errors

Do not update the membership pivot directly. Use `AddMemberAction`,
`ChangeMemberRoleAction`, `RemoveMemberAction`, and
`TransferOrganizationOwnershipAction`; direct pivot writes bypass application
invariants.

## Public leakage

Use `Organization::public()` for public discovery and keep application
serializers limited to explicitly public fields. Membership and workspace
payloads must be authorization-gated.
