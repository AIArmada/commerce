---
title: Contacting Overview
---

# Contacting Package

## What is Contacting?

The `aiarmada/contacting` package provides a reusable domain for attaching **contact methods** and **social profiles** to any entity in your Laravel application.

### Why it exists

Contact and social presence are not limited to people. The same concepts apply to organizations, NGOs, ministries, shops, businesses, groups, institutions, masjids, venues, branches, speakers, customers, and organizers.

This package allows any model to have:

**Contact methods:**
- Email, Phone, Mobile, WhatsApp, Website, Fax, Telegram, and other communication channels

**Social profiles:**
- Facebook, Instagram, TikTok, YouTube, LinkedIn, X/Twitter, Threads, Telegram channel/group, and other social/public profiles

### How it differs from other packages

| Package | Purpose |
|---|---|
| `aiarmada/contacting` | How to reach or follow an entity |
| `aiarmada/addressing` | Where the entity is physically/geographically |
| `aiarmada/engagement` or `aiarmada/sharing` | How content/entity is shared by users |

## Main Tables

- `contact_methods` — stores email, phone, WhatsApp, website, etc.
- `social_profiles` — stores Facebook, Instagram, TikTok, etc.
- `contact_snapshots` — preserves published/historical contact and social data

## Main Traits

- `HasContactMethods` — add to any Eloquent model to enable contact methods
- `HasSocialProfiles` — add to any Eloquent model to enable social profiles

## Main Actions

- `CreateContactMethodAction` — create contact methods with normalization
- `UpdateContactMethodAction` — update existing contact methods
- `SetPrimaryContactMethodAction` — set primary contact for a type/purpose
- `CreateSocialProfileAction` — create social profiles with handle/URL normalization
- `UpdateSocialProfileAction` — update social profiles
- `SetPrimarySocialProfileAction` — set primary social profile
- `BuildContactLinksAction` — generate mailto, tel, wa.me, website links
- `CreateContactSnapshotAction` — snapshot contact/social data for historical preservation
- `NormalizeContactMethodAction` — normalize email, phone, URL values
- `NormalizeSocialProfileAction` — normalize handles and URLs

## Example Entities

Any Eloquent model can use the traits:

```php
class Institution extends Model
{
    use HasContactMethods;
    use HasSocialProfiles;
}

class Venue extends Model
{
    use HasContactMethods;
    use HasSocialProfiles;
}

class Customer extends Model
{
    use HasContactMethods;
}
```