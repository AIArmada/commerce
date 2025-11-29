<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->number() }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
            padding: 40px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .company-info {
            max-width: 50%;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        .invoice-number {
            font-size: 16px;
            color: #666;
        }
        .invoice-date {
            color: #666;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
        }
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        .status-open {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-voided {
            background-color: #f8d7da;
            color: #721c24;
        }
        .customer-section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 12px;
            font-weight: 600;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .customer-name {
            font-weight: 600;
            font-size: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        th:last-child,
        td:last-child {
            text-align: right;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .totals {
            width: 300px;
            margin-left: auto;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .totals-row.total {
            font-size: 18px;
            font-weight: bold;
            border-bottom: none;
            border-top: 2px solid #333;
            margin-top: 10px;
            padding-top: 15px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 12px;
        }
        .checkout-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div class="company-name">{{ config('app.name', 'Company Name') }}</div>
            @if(config('cashier-chip.invoices.vendor_address'))
                <div>{{ config('cashier-chip.invoices.vendor_address') }}</div>
            @endif
        </div>
        <div class="invoice-info">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-number">#{{ $invoice->number() }}</div>
            @if($invoice->date())
                <div class="invoice-date">Date: {{ $invoice->date()->format('F j, Y') }}</div>
            @endif
            @if($invoice->dueDate())
                <div class="invoice-date">Due: {{ $invoice->dueDate()->format('F j, Y') }}</div>
            @endif
            @if($invoice->paid())
                <span class="status status-paid">Paid</span>
            @elseif($invoice->voided())
                <span class="status status-voided">Voided</span>
            @else
                <span class="status status-open">Open</span>
            @endif
        </div>
    </div>

    <div class="customer-section">
        <div class="section-title">Bill To</div>
        @if($invoice->customerName())
            <div class="customer-name">{{ $invoice->customerName() }}</div>
        @endif
        @if($invoice->customerEmail())
            <div>{{ $invoice->customerEmail() }}</div>
        @endif
        @if($invoice->customerPhone())
            <div>{{ $invoice->customerPhone() }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->invoiceItems() as $item)
                <tr>
                    <td>{{ $item->description() }}</td>
                    <td>{{ $item->quantity() }}</td>
                    <td>{{ $item->unitPriceFormatted() }}</td>
                    <td>{{ $item->totalFormatted() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span>Subtotal</span>
            <span>{{ $invoice->subtotal() }}</span>
        </div>
        @if($invoice->hasTax())
            <div class="totals-row">
                <span>Tax</span>
                <span>{{ $invoice->tax() }}</span>
            </div>
        @endif
        @if($invoice->hasDiscount())
            <div class="totals-row">
                <span>Discount</span>
                <span>-{{ $invoice->discount() }}</span>
            </div>
        @endif
        <div class="totals-row total">
            <span>Total</span>
            <span>{{ $invoice->total() }}</span>
        </div>
    </div>

    @if(!$invoice->paid() && $invoice->checkoutUrl())
        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ $invoice->checkoutUrl() }}" class="checkout-link">Pay Now</a>
        </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>Invoice generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>
</body>
</html>
