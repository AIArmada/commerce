<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Tests\Fixtures;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Ticketing\Contracts\TicketableInterface;
use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OwnedTicketable extends Model implements TicketableInterface
{
    use HasOwner;
    use HasUuids;

    protected $fillable = [
        'name',
    ];

    public function getTable(): string
    {
        return 'filament_ticketing_owned_ticketables';
    }

    /** @return MorphMany<TicketType, $this> */
    public function ticketTypes(): MorphMany
    {
        return $this->morphMany(TicketType::class, 'ticketable');
    }

    /** @return MorphMany<Pass, $this> */
    public function passes(): MorphMany
    {
        return $this->morphMany(Pass::class, 'ticketable');
    }

    public function effectivePricingMode(): PricingMode
    {
        return PricingMode::Paid;
    }

    public function transferWindowEndsAt(): ?CarbonImmutable
    {
        return null;
    }
}
