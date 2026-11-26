<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>1099-NEC preparation worksheet</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; line-height: 1.5; color: #182537; }
        h1 { font-size: 20pt; margin: 0 0 4pt; }
        h2 { font-size: 12pt; margin: 20pt 0 6pt; color: #34465b; }
        p { margin: 4pt 0; overflow-wrap: break-word; }
        .notice { padding: 10pt; border: 1pt solid #9aa7b5; background: #f5f7fa; margin: 14pt 0; }
        .amount { padding: 12pt; border: 1pt solid #9aa7b5; font-size: 16pt; font-weight: bold; }
        .notes { white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>1099-NEC preparation worksheet</h1>
    <p>Tax Year: {{ $year }}</p>
    <p class="notice">For preparation and review only (not an official IRS filing).</p>
    <h2>Payer information</h2>
    <p>Name: {{ $payerName }}</p>
    <p>Address: {{ $payerAddress }}</p>
    <h2>Recipient information</h2>
    <p>Name: {{ $recipientName }}</p>
    <p>Address: {{ $recipientAddress }}</p>
    <p>TIN: {{ $maskedTin }}</p>
    <h2>Reported compensation</h2>
    <p class="amount">{{ $amount }} ({{ $currency }})</p>
    @if ($excludedNotes !== null)
        <h2>Manual review notes</h2>
        <p class="notes">{{ $excludedNotes }}</p>
    @endif
</body>
</html>
