<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentCashierChip\Fixtures\User;
use AIArmada\Commerce\Tests\FilamentCashierChip\TestCase;
use AIArmada\FilamentCashierChip\Concerns\InteractsWithBillable;
use AIArmada\FilamentCashierChip\Pages\PaymentMethods;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

uses(TestCase::class);

it('returns the loaded current team billable when the authenticated user exposes currentTeam', function (): void {
    $team = new User;
    $team->forceFill([
        'name' => 'Team Account',
        'email' => 'team@example.test',
    ]);

    $user = new class extends User
    {
        public function currentTeam(): BelongsTo
        {
            return $this->belongsTo(User::class, 'id');
        }
    };

    $user->forceFill([
        'name' => 'Member',
        'email' => 'member@example.test',
    ]);
    $user->setRelation('currentTeam', $team);

    Auth::guard()->setUser($user);

    $probe = new class
    {
        use InteractsWithBillable;

        public function resolveBillable(): ?Model
        {
            return $this->getBillable();
        }
    };

    expect($probe->resolveBillable())->toBe($team);
});

it('gracefully handles billables without payment method mutator methods', function (): void {
    $billable = new class extends Model
    {
        protected $guarded = [];
    };

    $page = new class($billable) extends PaymentMethods
    {
        public function __construct(private readonly Model $testBillable) {}

        protected function getBillable(): ?Model
        {
            return $this->testBillable;
        }
    };

    $page->setAsDefault('pm_missing');
    $page->deletePaymentMethod('pm_missing');

    $notifications = session()->get('filament.notifications', []);
    $titles = collect($notifications)->pluck('title')->all();

    expect($titles)
        ->toContain(__('Unable to update payment method'))
        ->toContain(__('Unable to delete payment method'));
});
