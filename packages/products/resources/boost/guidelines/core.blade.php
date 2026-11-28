## AIArmada Products

Products owns catalog, variant, attribute and composite composition writes. Use named actions in `AIArmada\Products\Actions` rather than writing these records in sibling packages. Resolve the authorized owner before mutation; global writes require explicit global context when tenancy is enabled. `UpdateProduct` locks the current record and `EnsureProductBySlug` provisions within the exact owner scope.

Use `CreateCompositeProduct`, `ReplaceProductComponents` and `PublishCompositeProduct` for composite lifecycle operations. Consume canonical composite snapshots for cart and order integration, retaining exact component and variant identities. Use integer minor units for pricing and never reserve inventory against a composite parent.
