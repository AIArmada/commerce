<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Billing;

use AIArmada\CashierChip\Concerns\HandlesPaymentFailures;
use AIArmada\CashierChip\Concerns\InteractsWithChip;
use AIArmada\CashierChip\Concerns\ManagesCustomer;
use AIArmada\CashierChip\Concerns\ManagesInvoices;
use AIArmada\CashierChip\Concerns\ManagesPaymentMethods;
use AIArmada\CashierChip\Concerns\ManagesSubscriptions;
use AIArmada\CashierChip\Concerns\PerformsCharges;

trait Billable // @phpstan-ignore trait.unused
{
    use HandlesPaymentFailures;
    use InteractsWithChip;
    use ManagesCustomer;
    use ManagesInvoices;
    use ManagesPaymentMethods;
    use ManagesSubscriptions;
    use PerformsCharges;
}
