<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Concerns\ManagesCustomer;
use AIArmada\CashierChip\Concerns\ManagesInvoices;
use AIArmada\CashierChip\Concerns\ManagesPaymentMethods;
use AIArmada\CashierChip\Concerns\ManagesSubscriptions;
use AIArmada\CashierChip\Concerns\PerformsCharges;

trait Billable
{
    use Concerns\HandlesPaymentFailures;
    use ManagesCustomer;
    use ManagesInvoices;
    use ManagesPaymentMethods;
    use ManagesSubscriptions;
    use PerformsCharges;
}
