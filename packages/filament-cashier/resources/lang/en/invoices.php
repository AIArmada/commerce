<?php

declare(strict_types=1);

return [
    'title' => 'Invoices',
    'singular' => 'Invoice',
    'plural' => 'Invoices',

    'table' => [
        'number' => 'Number',
        'customer' => 'Customer',
        'gateway' => 'Gateway',
        'status' => 'Status',
        'amount' => 'Amount',
        'date' => 'Date',
        'due_date' => 'Due Date',
        'paid_at' => 'Paid At',
    ],

    'status' => [
        'paid' => 'Paid',
        'open' => 'Open',
        'draft' => 'Draft',
        'void' => 'Void',
        'uncollectible' => 'Uncollectible',
    ],

    'actions' => [
        'download' => 'Download PDF',
        'view' => 'View',
        'view_external' => 'View in :gateway Dashboard',
    ],

    'empty' => [
        'title' => 'No invoices yet',
        'description' => 'Invoices will appear here when subscriptions are processed.',
    ],
];
