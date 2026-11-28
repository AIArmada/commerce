<?php

declare(strict_types=1);

namespace AIArmada\Orders\Runtime;

use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

interface OrderInvoiceDownload
{
    public function download(string $orderId): Responsable | Response;
}
