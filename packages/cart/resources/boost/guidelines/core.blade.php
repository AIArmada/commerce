# Cart

Cart's durable resources are owned by the LiveCart, Conditions and Snapshots features. GuestMigration owns guest-to-user orchestration. Use the Cart facade/manager and storage contract for live cart operations; never write normalized snapshots as a substitute for mutating the live cart. Use the owning feature actions for stored-condition administration and checkout lifecycle. Preserve exact owner tuples, CAS revisions, integer minor-unit money and immutable buyable quotes. Never use a normalized read model as the authoritative checkout price.
