# Filament Customers development

- Register `AIArmada\FilamentCustomers\FilamentCustomersPlugin` on the panel.
- Customer and segment forms delegate writes to core `SaveCustomerDetails` and `SaveSegment`; relationship hooks delegate manual membership to `SyncManualSegmentMemberships`. Never use raw sync from a submitted form.
- Use `AIArmada\Customers\Features\Identity\Actions\MergeCustomers` directly from the merge page; use `RebuildAllSegments` for rebuild operations. Enforce both policy and owner scope for each record.
- Contacting and Addressing own contact and address persistence. Keep domain writes out of the Filament adapter.
