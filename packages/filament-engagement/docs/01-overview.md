---
title: Overview
---

## Introduction

`aiarmada/filament-engagement` is the Filament admin adapter for `aiarmada/engagement`. It provides Filament v5 resources, widgets, and reusable actions for managing follows, bookmarks, responses, reactions, subscriptions, and reminders.

## What this package owns

- Filament resources for follows, bookmarks, bookmark collections, responses, reactions, subscriptions, and reminders
- Reusable Filament actions: Follow, Bookmark, React, Respond, Subscribe, Set Reminder, Unfollow, Remove Bookmark
- Relation managers for embedding engagement data on other model resources (followers, bookmarks, reactions, responses, subscriptions, reminders)
- Engagement overview dashboard widget

## What this package does not own

- Engagement domain logic, persistence, or business rules; those stay in `aiarmada/engagement`
- The models being followed, bookmarked, or reacted to

## Registered Resources

| Resource | Model | Purpose | Pages |
|---|---|---|---|
| `FollowResource` | `Follow` | View and manage follows/followers | List |
| `BookmarkResource` | `Bookmark` | View bookmarks | List, View |
| `BookmarkCollectionResource` | `BookmarkCollection` | Manage bookmark collections | List, Create, Edit |
| `ResponseResource` | `Response` | View and manage RSVPs | List, View |
| `ReactionResource` | `Reaction` | View reactions | List, View |
| `SubscriptionResource` | `Subscription` | View and manage subscriptions | List, View |
| `ReminderResource` | `Reminder` | View and manage reminders | List, View |

## Reusable Actions

| Action | Purpose |
|---|---|
| `FollowAction` | Follow a record from any Filament table |
| `UnfollowAction` | Unfollow a record |
| `BookmarkAction` | Bookmark a record |
| `RemoveBookmarkAction` | Remove a bookmark |
| `ReactAction` | React to a record |
| `RespondAction` | RSVP to a record |
| `SubscribeAction` | Subscribe to updates |
| `SetReminderAction` | Set a reminder on a record |

## Relation Managers

| RelationManager | Purpose |
|---|---|
| `FollowersRelationManager` | Show followers of a model |
| `BookmarksRelationManager` | Show bookmarks of a model |
| `ReactionsRelationManager` | Show reactions on a model |
| `ResponsesRelationManager` | Show responses to a model |
| `SubscriptionsRelationManager` | Show subscriptions for a model |
| `RemindersRelationManager` | Show reminders on a model |

## Widgets

| Widget | Purpose |
|---|---|
| `EngagementOverviewWidget` | Dashboard stats: active follows, bookmarks, responses, subscriptions, due reminders |

## Related Packages

- `aiarmada/engagement` — core engagement domain package
- `aiarmada/commerce-support` — owner scoping support

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5+
- `aiarmada/engagement`
- `aiarmada/commerce-support`
