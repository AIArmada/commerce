# Filament Growth

Filament Growth provides administration and reporting for the Growth experiment aggregate; it owns no independent domain tables. Keep database mutations in named Actions, read-only analytics in Queries, and owner-scope decisions in Authorization. Resources, pages and widgets are presentation entry points. All mutable experiment and variant operations must respect owner scope and the configured authorization policies. Never re-open concluded or archived experiments through the running toggle.
