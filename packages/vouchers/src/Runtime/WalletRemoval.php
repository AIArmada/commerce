<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use Illuminate\Database\Eloquent\Model;

interface WalletRemoval
{
    public function handle(string $code, Model $holder): bool;
}
