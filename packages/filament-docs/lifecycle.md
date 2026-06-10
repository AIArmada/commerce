# Filament Docs Lifecycle

## 1. Bootstrap & Registration

### Service Provider (`FilamentDocsServiceProvider`)
- Registers package name `filament-docs` via `spatie/laravel-package-tools`.
- Publishes config file `filament-docs.php`, Blade views, and a route file (`filament-docs`).
- Binds `FilamentDocsPlugin` as a singleton and `RichContentRendererInterface` to `FilamentRichContentRenderer`.

### Plugin (`FilamentDocsPlugin`)
- Implements `Filament\Contracts\Plugin` with ID `filament-docs`.
- `register(Panel)` registers four resources, two custom pages, and five widgets — each individually togglable via fluent methods (`->agingReportEnabled(false)`, etc.).
- `boot(Panel)` is a no-op.
- Accepts overrides for all four resource class-strings (`->docResource(MyDocResource::class)`).
- Navigation group falls back to `config('filament-docs.navigation.group')` — default `'Documents'`.

### Config (`config/filament-docs.php`)
- `navigation.group` — panel navigation group name.
- `features.auto_generate_pdf` — whether document creation auto-generates a PDF (`false` by default).
- `resources.navigation_sort` — integer sort-order per resource/page (`docs`, `doc_templates`, `sequences`, `email_templates`, `pending_approvals`, `aging_report`).

---

## 2. Document Creation

### Entry Points
- `DocResource` list/create pages — standard Filament CRUD with `CreateDoc`, `ListDocs`, `EditDoc`, `ViewDoc`.
- `QuickActionsWidget` — six direct-create buttons for each `DocType` (Invoice, Quotation, Credit Note, Receipt, Delivery Note, Proforma Invoice) plus a link to the Aging Report page.

### Form (`DocForm`)
- **Document Information**: `doc_number` (auto-generate if empty), `doc_type` (live — resets template selection), `doc_template_id` (optional, owner-scoped), `status` (default `Draft`), `issue_date` (default now), `due_date` (auto-calculated), `currency` (default from `docs.defaults.currency`), `tax_rate`.
- **Customer Information** (collapsible): name, email, phone, address, city, state, postcode, country — stored as JSON `customer_data`.
- **Document Body** (visible when template uses `RichBody` block): Tiptap RichEditor with merge tags, file attachments, configured toolbar.
- **Line Items** (visible when template uses `LineItems` block): Repeater with name, quantity, unit price, description. Collapsible, cloneable, reorderable.
- **Amounts** (visible when template uses `Totals` block): `subtotal`, `tax_amount`, `discount_amount`, `total` — all auto-calculated if left empty.
- **Notes & Terms** (visible when template uses `NotesTerms` block): Two text areas.
- **Metadata**: KeyValue pair for arbitrary data (collapsed by default).

### Section Visibility by Template
Every body section checks the selected template's layout blocks via `TemplateBlockRegistry::hasBlock()`. If no template is selected, visibility falls back to `TemplateBlockRegistry::defaultLayout()`.

### Handler (`CreateDoc::handleRecordCreation`)
- Delegates to `DocService::create(DocData::from($data))`.
- Respects `auto_generate_pdf` config — `generate_pdf` key defaults from `config('filament-docs.features.auto_generate_pdf')`.

### Owner Auto-Assignment
- `Doc` model (in the `docs` package) uses `HasOwner`; owner is auto-assigned on create by the model trait when owner mode is enabled.
- `DocTemplate` and `DocSequence` and `DocEmailTemplate` creation pages inject `owner_type`/`owner_id` from `OwnerContext::resolve()` in `mutateFormDataBeforeCreate()` when `config('docs.owner.enabled')` is true.

---

## 3. Document Editing & Updates

### Handler (`EditDoc::handleRecordUpdate`)
- Checks `$record instanceof Doc`, then delegates to `DocService::update($record, $data)`.
- Non-Doc records fall through to parent `EditRecord::handleRecordUpdate`.

### Header Actions
- View action, Delete action.

### View Page (`ViewDoc`)
- **Header actions**: Edit, Preview Online (external route), Generate PDF (with confirmation modal), Share link creation (CheckboxList for allowed actions + expiry picker), Revoke share links (bulk), Download PDF (external route), Status action group (Mark as Sent, Mark as Paid, Cancel), Delete.
- **Share link creation**: Delegates to `DocRenderService::createShareLink($record, ShareLinkData)`. Returns the URL in a notification.
- **Revoke**: Sets `revoked_at` on all non-revoked share links.
- **Cancel**: Calls `$record->cancel()` — guarded by status check (not Paid, not already Cancelled).

### Record Actions (Table-level)
- View, Preview (external route), Edit, ActionGroup: Generate PDF, Record Payment, Mark as Sent, Mark as Paid, Delete.

### Bulk Actions
- Generate PDFs (all selected), Mark as Sent (all selected), Delete Selected.

---

## 4. Document Numbering (Sequences)

### `DocSequenceResource`
- Model: `DocSequence` (from `docs` package).
- Form fields: `name`, `doc_type` (select from `DocType` enum), `prefix`, `reset_frequency` (from `ResetFrequency` enum — e.g. yearly), `format` (tokens: `{PREFIX}`, `{NUMBER}`, `{YYYY}`, `{YY}`, `{MM}`, `{DD}`, `{YYMM}`, `{YYMMDD}`), `start_number`, `increment`, `padding`, `is_active`.
- Preview section shows next generated number via `$record->previewNextNumber()`.
- Table: columns for name, doc_type, prefix, format, reset_frequency, is_active (boolean icon), updated_at.
- Filters: doc_type (SelectFilter), is_active (TernaryFilter).
- Record actions: inline Edit.
- Bulk actions: Delete Selected.
- No view page — create / list / edit only.

### Lifecycle
1. Admin creates a sequence for a document type.
2. When `DocService::create()` processes a new document, it resolves the active sequence for the doc type and generates the next number.
3. The sequence counter increments; periodic reset happens based on `reset_frequency`.

---

## 5. Template Lifecycle

### `DocTemplateResource`
- Model: `DocTemplate` (from `docs` package).
- Uses `$tenantOwnershipRelationshipName = 'owner'` for Filament tenancy awareness.

### Form (`DocTemplateForm`)
- **Template Information**: name (live slug generation), slug (unique), description, doc_type (select from config `docs.types`), is_default toggle.
- **Layout Builder**: Filament `Builder` component with 10 block types:
  - `document_header` — visible toggle, heading label.
  - `parties` — visible toggle, company_label, customer_label.
  - `document_metadata` — visible toggle, label.
  - `rich_body` — visible toggle only (the actual body comes from the Doc).
  - `static_rich_text` — visible toggle, RichEditor with merge tags and file attachments.
  - `line_items` — visible toggle, label.
  - `totals` — visible toggle, label.
  - `notes_terms` — visible toggle, notes_label, terms_label.
  - `signature_payment` — visible toggle, label, body textarea.
  - `page_break` — visible toggle only.
  - `footer` — visible toggle, text.
- **Page & PDF Settings**: paper format (A4/A3/Letter/Legal), orientation (portrait/landscape), margins (top/right/bottom/left in mm), print background toggle.

### Infolist (`DocTemplateInfolist`)
- Template info, PDF settings, layout block summary, custom settings, usage statistics (count of documents using this template), timestamps.

### Table (`DocTemplatesTable`)
- Columns: name, slug, doc_type, blocks count, is_default, documents count (via `withCount('docs')`), created_at, updated_at.
- Filters: doc_type, is_default.
- Record actions: View, Edit, Set as Default (visible when not already default), Delete.
- Default sort: name ascending.

### Lifecycle
1. Admin creates a template with layout blocks and PDF settings.
2. When creating/editing a document, the form sections conditionally show/hide based on the selected template's blocks.
3. `TemplateBlockRegistry` resolves which sections to render.
4. PDF generation reads the template's layout and PDF settings to produce the output.
5. Setting a template as default (`$record->setAsDefault()`) unmarks others of the same doc_type.

---

## 6. Email Lifecycle

### `DocEmailTemplateResource`
- Model: `DocEmailTemplate` (from `docs` package).
- Uses `$tenantOwnershipRelationshipName = 'owner'`.

### Form
- **Template Settings**: name, slug (unique — scoped to owner via `scopeUniqueRuleToOwner()`), doc_type, trigger (send/due_soon/reminder/overdue/paid/created), is_active.
- **Email Content**: subject (with variable hints), body (RichEditor with merge tags).
- **Available Variables**: Collapsed reference section listing `{{doc_number}}`, `{{doc_type}}`, `{{customer_name}}`, `{{total}}`, `{{currency}}`, `{{due_date}}`, `{{issue_date}}`, `{{company_name}}`.

### Unique Slug Scoping (`scopeUniqueRuleToOwner`)
- When owner mode is enabled with `include_global`: slug must be unique within owner's records OR global records.
- When owner mode is enabled without `include_global`: slug must be unique within owner's records only.
- When owner mode is disabled: no additional scoping.

### Table
- Columns: name, doc_type, trigger, subject, is_active, updated_at.
- Filters: doc_type, trigger, is_active.
- Record actions: Edit, Duplicate (replicates with `(Copy)` suffix and unique timestamped slug).
- Bulk actions: Delete Selected.

### `SendEmailAction` (on DocResource)
- Modal form: `to` (defaults to `customer_data.email`), `cc`, `template_id` (filtered by doc_type, active only), `subject`, `message` textarea.
- Action delegates to `DocEmailService::send()`.
- Validates the selected template exists and is active for the doc's type.
- On failure: logged with context, danger notification shown.
- Visible only when the record has a recipient email.

### Emails Relation Manager
- Table: recipient, subject, status (sent/queued/failed/bounced badges), sent_at, open_count, click_count, first_open.
- Filters: status.
- Record actions: Resend (delegates to `DocEmailService::send()`), View.
- Default sort: sent_at descending.

---

## 7. Payment & Financial Lifecycle

### `RecordPaymentAction`
- Visible when document status is Sent, Pending, Overdue, or PartiallyPaid.
- Form: amount (max = remaining balance), payment_method (from `docs.payment_methods` config), reference, paid_at (max = now), notes.
- Validation: amount > 0, amount <= outstanding balance.
- Delegates to `DocService::recordPayment()`.
- Shows success notification with formatted amount.

### Payments Relation Manager
- Table: amount, payment_method, reference, transaction_id, paid_at, notes.
- Header action: Create (mutates currency from parent doc).
- Record actions: Edit, Delete.
- Bulk actions: Delete Selected.
- Default sort: paid_at descending.

### Revenue Tracking
- `RevenueChartWidget`: Queries `Doc` where status = Paid, groups `SUM(total)` by `DATE(paid_at)` for last 30 days. Renders as a line chart with currency-prefixed Y-axis.
- `DocStatsWidget`: Shows 5 stat cards — Total Documents, Draft count, Pending/Sent count, Paid count (with revenue), Overdue count (with outstanding). Uses `clone` of base query for independent counts.
- `StatusBreakdownWidget`: Doughnut chart across all 8 statuses (Draft, Pending, Sent, Paid, PartiallyPaid, Overdue, Cancelled, Refunded). Each slice uses the status's configured color.

---

## 8. Approval Lifecycle

### `PendingApprovalsPage`
- Accessible via `canAccess()` — requires `purchase.viewAny` ability.
- Shows only approvals where `assigned_to === Auth::id()` AND `status === 'pending'` AND the parent doc exists.
- Navigation badge: count of pending approvals for current user.
- Columns: doc_number (links to doc view), doc_type, recipient name, total (with currency), requested_by name, created_at, expires_at (red if past).
- Filters: doc_type.
- Actions: Approve (with optional comments, validates user is assignee), Reject (requires comments, validates user is assignee), View Document.
- Both approve/reject check `assertCanActOnApproval()`: user must be authenticated, must be the assigned user, approval must be pending, and the parent doc must exist.
- Header action: Refresh (resets table).

### Approvals Relation Manager (on DocResource)
- Form: requested_by (hidden, auto-set to auth user), assigned_to (select from owner's users or current user), expires_at, comments.
- Create action validates: user must be authenticated, assignee must be valid.
- Table: status, requested_by, assigned_to, comments, expires_at, approved_at, rejected_at, created_at.
- Filters: status.
- Record actions: Approve (visible when pending and user can act — if unassigned, any auth user can act; if assigned, only that user), Reject (same visibility rules, requires comments).
- `approve()` and `reject()` are called on the `DocApproval` model directly.
- Bulk actions: Delete Selected.

---

## 9. Reporting & Export Lifecycle

### `AgingReportPage`
- Access requires `purchase.viewAny`.
- Query: documents with status Pending, Sent, PartiallyPaid, Overdue — only those with a non-null `due_date`.
- Columns: doc_number, doc_type, customer name, issue_date, due_date, days_overdue (computed, color-coded: 0=success, ≤30=warning, ≤60=orange, >60=danger), aging_bucket (Current, 1-30, 31-60, 61-90, 90+), amount (with currency), status.
- Filters: aging_bucket (query modifier using `whereBetween`/`where` on `due_date`), status.
- `getAgingSummary()`: Returns `{bucket => {count, amount}}` for header cards — iterates all matching documents and computes days overdue per document.
- Record actions: View link to doc resource.

### `DocExporter`
- Extends `Filament\Actions\Exports\Exporter`.
- Modifies query to add `withSum('payments as paid_amount', 'amount')`.
- Columns: doc_number, doc_type, status (label format), customer_name (from JSON), customer_email (from JSON), issue_date, due_date, currency, subtotal, tax_amount, discount_amount, total, paid_amount (from computed sum), notes, created_at, updated_at.
- Custom completion notification body.

### Export action on Doc table
- Header action using `ExportAction::make()->exporter(DocExporter::class)`.

---

## 10. Rendering & PDF Lifecycle

### Rich Content Rendering
- `FilamentRichContentRenderer` implements `RichContentRendererInterface` from `docs` package.
- Uses Filament's `RichContentRenderer` with:
  - `DocsRichContentFileAttachmentProvider` for file attachment URL resolution.
  - Config-driven disk and visibility (`docs.storage.disk`, `docs.storage.rich_content_visibility`).
  - Custom node processor: strips `id` from image nodes; removes `src` if the ID is not in `DocRichContentStorage::isAllowedFileId()`.
- `DocsRichContentFileAttachmentProvider`:
  - Returns `temporaryUrl()` for private visibility, `url()` for public.
  - Validates file existence before returning URL.
  - Throws on `saveUploadedFileAttachment()` — uploads are handled by the Filament field, not the renderer.

### PDF Generation
- Triggered via:
  - `ViewDoc` header action "Generate PDF" (with confirmation modal).
  - `DocsTable` record action "Generate PDF" (inline, no modal).
  - `DocsTable` toolbar bulk action "Generate PDFs".
  - Auto on create when `config('filament-docs.features.auto_generate_pdf')` is true.
- All paths delegate to `DocService::generatePdf($record, save: true)`.
- Success notification shown after generation.

### Online Preview
- Route: `filament-docs.documents.view` (named route).
- Accessible from table "Preview" action and view page "Preview Online" action.
- Both open in a new tab.

### PDF Download
- Route: `filament-docs.download` (named route).
- Accessible from view page "Download PDF" action.
- Opens in a new tab.

### Share Links
- Created from `ViewDoc` header action.
- `DocRenderService::createShareLink()` creates a `ShareLink` model.
- Actions: `View` (online preview) or `Pdf` (download).
- Route chosen based on allowed actions — `docs.share.show` for view, `docs.share.pdf` for PDF-only.
- Public URL returned in notification.

---

## 11. Versioning Lifecycle

### Versions Relation Manager
- Read-only listing of `DocVersion` records.
- Columns: version_number (badge), change_summary, changed_by, created_at.
- Record actions: View (modal with version snapshot partial), Restore (requires confirmation, calls `$record->restore()`, dispatches `refresh`).
- Default sort: version_number descending.

---

## 12. Status History Lifecycle

### Status Histories Relation Manager
- Read-only listing of status change records.
- Columns: status (with color badge), notes, changed_by, created_at.
- Default sort: created_at descending.
- Paginated with options [10, 25, 50].

---

## 13. Authorization & Owner Scoping

### Authorization
All four resources and both custom pages gate access via `FilamentPermission`:
- `canViewAny()` / `canAccess()` — requires `purchase.viewAny`.
- `canView()` — requires `purchase.view`.
- `canCreate()` — requires `purchase.create` OR `purchase.viewAny`.
- `canEdit()` — requires `purchase.update` OR `purchase.viewAny`.
- `canDelete()` — requires `purchase.delete` OR `purchase.viewAny`.
- `shouldRegisterNavigation()` — delegates to `canViewAny()` / `canAccess()`.

### Owner Scoping
- `DocResource::getEloquentQuery()` — applies `OwnerUiScope::apply($query, includeGlobal: false)`.
- `DocsOwnerScope` — thin wrapper around `OwnerUiScope` for record-level access/mutation assertions with `NotFoundHttpException`.
- `DocEmailTemplateResource::scopeUniqueRuleToOwner()` — scopes unique slug validation to owner context.
- `CreateDocTemplate`, `CreateDocSequence`, `CreateDocEmailTemplate` — inject owner tuple via `mutateFormDataBeforeCreate()`.
- `ApprovalsRelationManager::resolveAssignableUserOptions()` — resolves users from owner relation or falls back to current user.

---

## 14. Caching

### Navigation Badge Cache
- `DocResource::cachedBadgeCounts()` — cached for 30 seconds, key `filament-docs:nav-badge:counts`.
- Computes pending + overdue counts via raw `SUM(CASE WHEN)` query on the owner-scoped query.
- Badge shows total of pending + overdue; color is `danger` if any overdue exist, otherwise `warning`.
