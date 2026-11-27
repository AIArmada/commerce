# Customers development

- `AIArmada\Customers\Models\Customer` is an owner-scoped commercial profile; shared person identity belongs to persons. Use `Features\Identity\Actions\LinkCustomerToPerson` for identity linkage.
- Use `Features\Identity\Actions\CreateCustomer` and `UpdateCustomerProfile` for checkout integration; use `SaveCustomerDetails` for explicit customer profile, status and marketing-consent changes.
- Use `Features\Segmentation\Models\Segment` and the owning actions (`SaveSegment`, `AssignCustomerToSegment`, `RemoveCustomerFromSegment`, `RebuildAllSegments`, `SyncManualSegmentMemberships`). Never edit membership pivots directly from UI.
- Use `Features\Segmentation\Actions\ManageCustomerGroupMembership` for group role and membership changes and `Features\Identity\Actions\MergeCustomers` for merging profiles. Resolve owners and validate references before writes.
- Contacting owns email and phone; Addressing owns addresses. Payment integration uses `AIArmada\Customers\Drivers\CustomersPaymentSubjectDriver`. Never duplicate contact or address data in customer columns.

- Customer notes are owned by Identity (`Features\Identity\Models\CustomerNote`); mutate them through `Features\Identity\Actions\ManageCustomerNote`.
