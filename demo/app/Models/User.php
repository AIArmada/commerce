<?php

declare(strict_types=1);

namespace App\Models;

use AIArmada\Cashier\Concerns\Billable as UnifiedBillable;
use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\CashierChip\Billing\Billable as ChipBillable;
use AIArmada\CashierChip\Billing\BillableModel;
use AIArmada\Orders\Models\Order;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $avatar
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class User extends Authenticatable implements BillableContract, BillableModel, FilamentUser
{
    use ChipBillable;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;
    use UnifiedBillable {
        UnifiedBillable::onGenericTrial insteadof ChipBillable;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function customerName(): ?string
    {
        return $this->chipName();
    }

    public function customerEmail(): ?string
    {
        return $this->chipEmail();
    }

    public function customerPhone(): ?string
    {
        return $this->chipPhone();
    }

    /** @return array<string, mixed> */
    public function customerAddress(): array
    {
        return $this->chipAddress();
    }

    public function preferredLocale(): ?string
    {
        return app()->getLocale();
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
