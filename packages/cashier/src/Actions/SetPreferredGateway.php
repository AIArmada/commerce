<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Support\ActionGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LogicException;

final class SetPreferredGateway
{
    public function handle(Model $billable, string $gateway): void
    {
        $gateway = ActionGuard::gatewayName($gateway);

        if ($billable instanceof BillableContract) {
            ActionGuard::assertBillableInScope($billable);
        }

        if (! Schema::connection($billable->getConnectionName())->hasColumn($billable->getTable(), 'preferred_gateway')) {
            throw new LogicException('Billable model [' . $billable::class . '] must have a preferred_gateway column to persist the preferred gateway.');
        }

        $billable->setAttribute('preferred_gateway', $gateway);
        $billable->save();
    }
}
