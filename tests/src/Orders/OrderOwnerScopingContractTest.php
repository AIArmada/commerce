<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Orders;

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\CommerceSupport\Tests\Fixtures\TestOwner;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class OrderOwnerScopingContractTest extends TestCase
{
    use OwnerScopingContractTests;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('test_owners');
        Schema::create('test_owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        config()->set('orders.owner.enabled', true);
        config()->set('orders.owner.include_global', true);
        config()->set('orders.owner.auto_assign_on_create', false);

        app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
        {
            public function resolve(): ?Model
            {
                return null;
            }
        });
    }

    protected function getModelClass(): string
    {
        return Order::class;
    }

    protected function createOwner(): Model
    {
        return TestOwner::query()->create([
            'name' => 'Order Contract Owner',
        ]);
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner($owner, function () use ($owner): Order {
            return Order::factory()->create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ]);
        });
    }

    protected function createGlobalModel(): Model
    {
        return OwnerContext::withOwner(null, function (): Order {
            return Order::factory()->create([
                'owner_type' => null,
                'owner_id' => null,
            ]);
        });
    }

    public function test_assign_owner_sets_owner(): void
    {
        $owner = $this->createOwner();
        $model = new Order;

        OwnerContext::withOwner($owner, function () use ($model, $owner): void {
            $model->assignOwner($owner);
            $model->save();
        });

        expect($model->hasOwner())->toBeTrue()
            ->and($model->belongsToOwner($owner))->toBeTrue();
    }
}
