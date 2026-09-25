---
title: Usage
---

# Usage

## Managing links

The Links resource provides full CRUD:

- **Name and slug** — the slug is globally unique; leave it blank to auto-generate.
- **Destination URL** — validated against the core package's security rules (`https`, allowed hosts).
- **UTM defaults** — merged into the destination on redirect.
- **Destination parameters** — always merged into the destination; these win over incoming query values.
- **Signed URLs** — when required, only valid signed URLs redirect (unsigned hits return `403`).
- **Limits** — maximum human clicks and expiry date.
- **Subject** — shown on the table for links owned by consumer packages (for example affiliate offer links).

## Row actions

- **Open** — visits the shareable URL in a new tab (signed automatically when the link requires it; records a real click).
- **Deactivate / Reactivate** — toggles the link without deleting its history.
- **Edit / Delete** — deleting a link also deletes its clicks.

## Click history

Open a link's edit page and scroll to the Clicks relation manager for per-click device, browser, OS, referrer, source, campaign, ad click IDs, and bot flags, newest first.

## Read next

- [Troubleshooting](99-troubleshooting.md)
