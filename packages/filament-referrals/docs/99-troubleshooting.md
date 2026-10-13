---
title: Filament Referrals Troubleshooting
---

# Filament Referrals Troubleshooting

## A program, invite, or reward is missing

The current owner context determines which programs are visible. Invites and rewards are scoped through those programs. Confirm that the host has bound an OwnerResolverInterface and that the current request resolves the owner that created the program.

## An action refuses a row visible in the table

The action re-resolves the record through the core guard at submission time. An owner change or stale page can make the record unavailable between render and submit. Refresh under the expected owner context before retrying.

## Fulfilment reports a failure

The core Action returns a failed reward or throws a fulfilment exception. Check the configured driver and, for manual fulfilment, add the configured external-reference metadata before retrying. See the core [referrals troubleshooting guide](../../referrals/docs/99-troubleshooting.md).

## Navigation values are not applied

Confirm the published filament-referrals.php file contains the nested navigation.group and navigation.sort keys, then clear the application config cache.

## An invitee contact is not shown

The adapter deliberately never displays the decrypted invitee contact or its token hash. Use the host delivery system to manage contact workflows.
