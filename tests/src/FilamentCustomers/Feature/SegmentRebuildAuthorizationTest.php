<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Customers\Models\Segment;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class TestHasTableComponent extends Component implements HasTable
{
    use InteractsWithTable;

    public function getTable(): Table
    {
        return Table::make($this);
    }

    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    public function render()
    {
        return view('livewire.placeholder');
    }
}

it('rebuild action honors Gate interceptors', function (): void {
    config()->set('customers.features.owner.enabled', false);

    $user = User::query()->create([
        'name' => 'Segment Rebuilder',
        'email' => 'segment-rebuilder-' . uniqid() . '@example.com',
        'password' => 'password',
    ]);

    test()->actingAs($user);

    $segment = Segment::query()->create([
        'name' => 'Manual Segment',
        'slug' => 'manual-segment-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => false,
        'is_active' => true,
    ]);

    Gate::before(fn (mixed $authenticatedUser, string $ability): ?bool => $authenticatedUser?->is($user) === true && $ability === 'rebuild'
        ? true
        : null);

    $livewire = new TestHasTableComponent();
    $table = SegmentResource::table(Table::make($livewire));

    $rebuildAction = $table->getAction('rebuild');
    $rebuildAction->livewire($livewire);
    $rebuildAction->record($segment);

    expect(fn (): mixed => $rebuildAction->call())->not->toThrow(Throwable::class);
});
