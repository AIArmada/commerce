# Filament Events

Register the FilamentEventsPlugin on the host panel. This package is a UI adapter for aiarmada/events; domain state and transactional mutations belong to Events. For approval requests use EventApprovalWorkflow with the authenticated panel actor and scalar request ID. Keep resource queries owner-scoped and revalidate submitted IDs on the server. Venue and taxonomy catalogs are intentionally global.
