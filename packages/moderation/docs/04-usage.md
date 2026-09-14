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

`block()` resolves the actor model safely when an ID and class name are provided. If you do not pass an actor, the block is still recorded. Morph-map aliases are resolved to their mapped model class before lookup.

Re-blocking an already-blocked model is idempotent: the existing active block is returned instead of stacking a duplicate row, and its expiry is extended when the new expiry is later. An indefinite block (no expiry) stays indefinite. Past-due active rows are ignored by the lookup, so blocking again after expiry creates a fresh block.

The reason must be a known `BlockReason` value; unknown strings throw an `InvalidArgumentException` instead of being recorded as `other`. Any date implementing `CarbonInterface` is accepted for the expiry and normalized to an immutable instance.

## Restricting actor types

`blockedByType` and `actionedByType` accept any `Model` subclass by default, and owner-scoped actors are validated against the current owner. Never forward request input as the type. When the host wants a hard boundary, list the permitted actor classes:

```php
'actors' => [
    'allowed_types' => [
        App\Models\Admin::class,
    ],
],
```

With a non-empty list, any other actor type throws an `InvalidArgumentException`.

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
    notes: 'Flagged by the keyword filter',
);
```

The reason is required and capped at 255 characters; optional notes (up to 10000 characters) and metadata (up to 50 entries and 65535 bytes encoded) ride along on the record.

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

Expired blocks are excluded from `active()` queries as soon as `expires_at` passes, and the `expired()` scope includes both transitioned rows and past-due active rows. Run the sweep command from a scheduler when you need persisted `expired` status and lifecycle timestamps updated:

```bash
php artisan moderation:expire-blocks
```

The package does not register a scheduler entry automatically. If the application wants a periodic sweep, schedule the command in its application scheduler:

```php
use Illuminate\Console\Scheduling\Schedule;

protected function schedule(Schedule $schedule): void
{
    $schedule->command('moderation:expire-blocks')->daily();
}
```

The command processes each owner scope explicitly; it does not rely on ambient web authentication.

```php
use Illuminate\Database\Eloquent\Model;

final class Comment extends Model
{
    use HasBlocks;
}

$visibleComments = Comment::query()->whereNotBlocked()->get();
```

## Owner-scoped models

When owner mode is enabled, set an owner context before creating or querying moderation records. The package validates owner-scoped targets and actors through `commerce-support` guards; use explicit global context for intentional global moderation. Models without owner scoping are intentionally blockable from any owner scope: a tenant blocks a shared identity without owning it.

## Lifting a block

```php
$block->transitionTo(BlockStatus::Lifted, at: CarbonImmutable::now(), liftedBy: $admin)->save();
```

Pass the lifting actor to record the `lifted_by` audit columns. Re-activating a lifted block clears the lift attribution. Deleting a blockable expires its active blocks from every owner scope in one transaction using a mass update, so `Block` model events do not fire for that cleanup.
