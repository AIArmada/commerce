---
title: Usage
---

# Usage

## Managing links

The Links resource provides full CRUD:

- **Name and slug** — the slug is globally unique; leave it blank to auto-generate.
- **Destination URL** — validated against the core package's security rules (`https`, allowed hosts).
- **UTM defaults** — merged into the destination on redirect.
- **Limits** — maximum human clicks and expiry date.

## Row actions

- **Open** — visits the cloaked URL in a new tab (records a real click).
- **Deactivate / Reactivate** — toggles the link without deleting its history.
- **Edit / Delete** — deleting a link also deletes its clicks.

## Click history

Open a link's edit page and scroll to the Clicks relation manager for per-click device, browser, OS, referrer, source, campaign, and bot flags, newest first.

## Read next

- [Troubleshooting](99-troubleshooting.md)
