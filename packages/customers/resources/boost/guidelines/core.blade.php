# Customers development

- `AIArmada\Customers\Models\Customer` is an owner-scoped commercial profile; shared person identity belongs to persons. Use `Features\Identity\Actions\LinkCustomerToPerson` for identity linkage.
- Use `Features\Identity\Actions\CreateCustomer` and `UpdateCustomerProfile` for checkout integration; use `SaveCustomerDetails` for explicit customer profile, status and marketing-consent changes.
- Use `Features\Segmentation\Models\Segment` and the owning actions (`SaveSegment`, `AssignCustomerToSegment`, `RemoveCustomerFromSegment`, `RebuildAllSegments`, `SyncManualSegmentMemberships`). Never edit membership pivots directly from UI.
- Use `Features\Segmentation\Actions\ManageCustomerGroupMembership` for group role and membership changes and `Features\Identity\Actions\MergeCustomers` for merging profiles. Resolve owners and validate references before writes.
- Contacting owns email and phone; Addressing owns addresses. Payment integration uses `AIArmada\Customers\Features\Identity\Services\CustomersPaymentSubjectDriver`. Never duplicate contact or address data in customer columns.

- Customer notes are owned by Identity (`Features\Identity\Models\CustomerNote`); mutate them through `Features\Identity\Actions\ManageCustomerNote`.

- Customer groups are owned by Segmentation (`Features\Segmentation\Models\CustomerGroup`); use `Features\Segmentation\Actions\SaveCustomerGroup` for group lifecycle writes and `ManageCustomerGroupMembership` for membership changes.

- Delete customers through `Features\Identity\Actions\DeleteCustomer::execute`, segments through `Features\Segmentation\Actions\SaveSegment::delete`, and groups through `Features\Segmentation\Actions\SaveCustomerGroup::delete`. These actions lock the parent and roll back database changes if deletion is cancelled.
