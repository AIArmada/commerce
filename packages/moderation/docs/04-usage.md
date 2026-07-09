---
title: Usage
---

# Usage

## Blocking a model

```php
use AIArmada\Moderation\Traits\HasBlocks;
use Illuminate\Database\Eloquent\Model;

final class Comment extends Model
{
    use HasBlocks;
}

$comment->block(
    reason: 'spam',
    notes: 'Repeated link drops',
    blockedById: (string) $admin->getKey(),
    blockedByType: $admin::class,
);
```

`block()` resolves the actor model safely when an ID and class name are provided. If you do not pass an actor, the block is still recorded.

## Recording a moderation action

```php
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Traits\HasModerationActions;
use Illuminate\Database\Eloquent\Model;

final class Comment extends Model
{
    use HasModerationActions;
}

$comment->recordModerationAction(
    type: ModerationActionType::Warn,
    reason: 'Review required',
);
```

### Action types

| Case | Value | Typical use |
| --- | --- | --- |
| `Warn` | `warn` | Soft warning without a block |
| `Mute` | `mute` | Temporary silence |
| `Suspend` | `suspend` | Temporary account restriction |
| `Ban` | `ban` | Permanent restriction |
| `LiftBlock` | `lift_block` | Record that a block was lifted |
| `Approve` | `approve` | Content or submission approved |
| `Reject` | `reject` | Content or submission rejected |
| `ChangesRequested` | `changes_requested` | Reviewer requested edits |
| `Cancelled` | `cancelled` | Moderation flow cancelled |
| `RevertedToDraft` | `reverted_to_draft` | Item returned to draft |
| `Reconsidered` | `reconsidered` | Prior decision reopened |
| `Remoderated` | `remoderated` | Item sent through moderation again |

`ModerationAction` is intentionally non-final so domain packages can extend the model when they need package-specific action surfaces.

## Working with queries

```php
use AIArmada\Moderation\Models\Block;

$activeBlocks = Block::query()->active()->get();
$expiredBlocks = Block::query()->expired()->get();
```

```php
use Illuminate\Database\Eloquent\Model;

final class Comment extends Model
{
    use HasBlocks;
}

$visibleComments = Comment::query()->whereNotBlocked()->get();
```

## Owner-scoped models

When owner mode is enabled, set an owner context before creating or querying moderation records. The package validates owner-scoped targets and actors through `commerce-support` guards; use explicit global context for intentional global moderation.
