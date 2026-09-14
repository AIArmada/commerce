<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Tests\Fixtures;

use AIArmada\Engagement\Contracts\HasSubscriptionMatchContext;
use AIArmada\Engagement\Contracts\Subscribable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class EngagementContextSubject extends Model implements HasSubscriptionMatchContext, Subscribable
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

    public function subscribableName(): string
    {
        return (string) ($this->getAttribute('name') ?? 'Engagement context subject');
    }

    /** @return array<string> */
    public function availableSubscriptionTypes(): array
    {
        return ['updates'];
    }

    public function defaultSubscriptionNotificationLevel(): ?string
    {
        return 'all';
    }

    /** @return array<string, mixed> */
    public function subscriptionMatchContext(): array
    {
        return ['tier' => 'gold'];
    }
}
