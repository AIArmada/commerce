<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Tests\Fixtures;

use AIArmada\Engagement\Contracts\Bookmarkable;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Contracts\Reactable;
use AIArmada\Engagement\Contracts\Remindable;
use AIArmada\Engagement\Contracts\Respondable;
use AIArmada\Engagement\Contracts\Shareable;
use AIArmada\Engagement\Contracts\Subscribable;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class EngagementSubject extends Model implements Bookmarkable, Followable, Reactable, Remindable, Respondable, Shareable, Subscribable
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'email', 'password'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (! isset($this->attributes[$this->getKeyName()])) {
            $this->setAttribute($this->getKeyName(), (string) Str::uuid());
        }
    }

    public function getTable(): string
    {
        return 'users';
    }

    public function getMorphClass(): string
    {
        return static::class;
    }

    public function followableName(): string
    {
        return (string) ($this->getAttribute('name') ?? 'Engagement subject');
    }

    public function followableUrl(): ?string
    {
        return 'https://example.test/engagement-subjects/' . $this->getKey();
    }

    public function followableImage(): ?string
    {
        return null;
    }

    public function defaultFollowNotificationLevel(): ?string
    {
        return 'all';
    }

    public function bookmarkTitle(): string
    {
        return $this->followableName();
    }

    public function bookmarkUrl(): ?string
    {
        return $this->followableUrl();
    }

    public function bookmarkImage(): ?string
    {
        return null;
    }

    public function allowedReactionTypes(): array
    {
        return ['like', 'love'];
    }

    public function allowsMultipleReactionTypesFromSameReactor(): bool
    {
        return false;
    }

    public function allowedResponseTypes(): array
    {
        return ['interested', 'going', 'maybe'];
    }

    public function defaultResponseVisibility(): string
    {
        return 'public';
    }

    public function allowsMultipleResponsesFromSameResponder(): bool
    {
        return false;
    }

    public function remindableName(): string
    {
        return $this->followableName();
    }

    public function reminderAnchorTime(string $anchorType, ?string $anchorCode = null): ?DateTimeInterface
    {
        return CarbonImmutable::now()->addHour();
    }

    public function allowedReminderTypes(): array
    {
        return ['before_start', 'follow_up', 'event'];
    }

    public function shareTitle(): string
    {
        return $this->followableName();
    }

    public function shareUrl(): string
    {
        return $this->followableUrl() ?? 'https://example.test';
    }

    public function shareDescription(): ?string
    {
        return 'Engagement subject';
    }

    public function shareImage(): ?string
    {
        return null;
    }

    public function subscribableName(): string
    {
        return $this->followableName();
    }

    public function availableSubscriptionTypes(): array
    {
        return ['updates'];
    }

    public function defaultSubscriptionNotificationLevel(): ?string
    {
        return 'all';
    }
}
