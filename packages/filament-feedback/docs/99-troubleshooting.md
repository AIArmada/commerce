---
title: Troubleshooting
---

# Troubleshooting

## Resources not appearing

Ensure the plugin is registered on the panel and the resource is enabled in `config/filament-feedback.php`.

## Policy denial

If actions are not visible, check that the current user has the required policy permissions and an owner context is resolved.

## Owner context missing

Widgets and exports require an owner context. Ensure the panel user resolves to an owner, or use `OwnerContext::withOwner()`.

## Empty widgets

Widgets apply owner scoping. If no data is visible, verify that the current owner has forms and responses.

## Export missing rows

Export queries are owner-scoped. Ensure the exporter user context matches the expected owner.

## Question builder validation errors

Question keys must be unique per form. Check for duplicate keys when using the builder.
