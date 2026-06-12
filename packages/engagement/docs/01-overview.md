---
title: Engagement Overview
---

## Introduction

`aiarmada/engagement` stores user intent and preference toward any model in your application. It provides a complete engagement layer: following, bookmarking, responding (RSVP), reactions, subscriptions, reminders, and sharing — all fully polymorphic.

## What this package owns

- Follow/unfollow/mute/unfollow lifecycle on any model
- Bookmarking with organized collections
- Response/RSVP tracking (interested, going, maybe, not going)
- Lightweight reactions (like, love, useful, support, insightful)
- Rule/filter-based subscriptions with criteria matching
- Scheduled reminders with Laravel Notifications delivery
- Share tracking (WhatsApp, Telegram, email, etc.)
- Interaction counters for followers, bookmarks, responses, reactions
- Console commands for delivering due reminders and matching subscriptions
- Events package integration for engagement-driven event recommendations

## What this package does not own

- User/actor management or authentication
- The content/models being followed, bookmarked, or reacted to
- Filament admin surfaces; those belong to `aiarmada/filament-engagement`

## Core Concepts

| Concept | Description |
|---|---|
| **Follow** | Entity-based interest tracking — follow a speaker, event, topic |
| **Bookmark** | Save items for later, organized into named collections |
| **Response** | Explicit RSVP intent — interested, going, maybe, not going |
| **Reaction** | Lightweight feedback — like, love, useful, support, insightful |
| **Subscription** | Rule/filter-based interest — "notify me about online events" |
| **Reminder** | Scheduled notification before an event/time |
| **Share** | Tracked share actions to external platforms |

## Key Features

- Fully polymorphic — works with any Eloquent model
- Duplicate prevention for active follows, bookmarks, and responses
- Lifecycle tracking — never deletes rows, transitions through statuses
- Bookmark collections with sorting and visibility control
- Subscription criteria matching engine for content-based notifications
- Reminder delivery schedule with Laravel Notifications
- Share tracking with unique tokens and channel attribution
- Interaction counters with follower/bookmark/reaction/response counts
- Events package integration for engagement-driven event recommendations
- 7 service contracts, all with default implementations

## Contracts and Services

| Contract | Default Service | Purpose |
|---|---|---|
| `EngagementManager` | `DefaultEngagementManager` | Central facade: follow, bookmark, respond, react, remind, share |
| `SubscriptionManager` | `DefaultSubscriptionManager` | Subscribe, unsubscribe, mute, match subscriptions |
| `ReminderManager` | `DefaultReminderManager` | Set, cancel, due reminders, mark sent/failed |
| `EngagementStateResolver` | `DefaultEngagementStateResolver` | Query state: isFollowing, isBookmarked, responseFor, reactionFor |
| `EngagementCounterService` | `DefaultEngagementCounterService` | Count followers, bookmarks, responses, reactions |
| `EngagementPolicyResolver` | `DefaultEngagementPolicyResolver` | Resolve engagement policies per model |
| `ShareUrlGenerator` | `DefaultShareUrlGenerator` | Generate share URLs for platforms |

## Models

| Model | Purpose | Statuses |
|---|---|---|
| `Follow` | Entity-based interest | active, muted, unfollowed, blocked |
| `Bookmark` | Saved items | active, removed, archived |
| `BookmarkCollection` | Named bookmark groups | active, archived, locked |
| `BookmarkCollectionItem` | Bookmark-to-collection join | — |
| `Response` | RSVP intent | active, changed, cancelled, expired |
| `Reaction` | Lightweight feedback | active, removed |
| `Subscription` | Rule/filter interest | active, muted, unsubscribed, expired |
| `Reminder` | Scheduled notification | pending, scheduled, sent, failed, cancelled |
| `Share` | Tracked share action | created, shared, revoked, expired, failed |
| `EngagementCounter` | Cached interaction counts | — |

## Traits

Apply to your models to add engagement capabilities:

| Trait | Adds |
|---|---|
| `CanFollow` | Actor can follow other models |
| `CanBookmark` | Actor can bookmark models |
| `CanRespond` | Actor can RSVP to models |
| `CanReact` | Actor can react to models |
| `CanSubscribe` | Actor can create subscriptions |
| `CanSetReminders` | Actor can set reminders |
| `CanShare` | Actor can share models |
| `HasFollowers` | Model can be followed |
| `HasBookmarks` | Model can be bookmarked |
| `HasReactions` | Model can be reacted to |
| `HasResponses` | Model can be responded to |
| `HasReminders` | Model can have reminders |
| `HasShares` | Model can be shared |
| `HasSubscriptions` | Model can have subscribers |

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support`
