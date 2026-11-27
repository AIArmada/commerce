---
name: links-development
description: Integrate and develop links using Laravel Dune package boundaries and owner-scoped actions.
---

# Links development

- Manage tracked links through CreateLink, CreatePublicLink, UpdateLink, DeactivateLink and ReactivateLink. These actions live under AIArmada\Links\Actions.
- The public redirect routes retain their configured names and signed URL semantics; RedirectToLinkController is an HTTP entry point, not a domain API.
- Implement LinkGateInterface and optional FallbackRedirectGateInterface using AIArmada\Links\Data\LinkGateContext. Register gate implementations with the LinkGateInterface container tag.
- Record clicks with RecordLinkClick. It writes counters and click rows in one locked transaction and refuses exhausted or inactive links.
- The public slug namespace is global. Management is owner-scoped; never use unscoped queries for an authenticated admin write.
- Run links:prune-clicks for bounded retention cleanup. Do not duplicate tracking writes or call the device parser implementation directly.
