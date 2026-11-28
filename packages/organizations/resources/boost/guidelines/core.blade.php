# Organizations

Organizations owns organization lifecycle and ownership invariants. Invoke CreateOrganizationAction, UpdateOrganizationAction, ArchiveOrganizationAction, RestoreOrganizationAction, SuspendOrganizationAction, MakeOrganizationPublicAction, MakeOrganizationPrivateAction and TransferOrganizationOwnershipAction. Resolve actor and organization explicitly; lifecycle mutations authorize under a row lock. The current.organization middleware alias establishes OwnerContext. Use membership actions for ordinary members; only the ownership-transfer workflow may replace the owner.

Do not introduce compatibility wrappers, dual writes, or backfills.
