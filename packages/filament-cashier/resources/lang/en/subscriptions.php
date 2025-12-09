<?php

declare(strict_types=1);

return [
    'title' => 'Subscriptions',
    'singular' => 'Subscription',
    'plural' => 'Subscriptions',

    'table' => [
        'user' => 'Customer',
        'gateway' => 'Gateway',
        'type' => 'Type',
        'plan' => 'Plan',
        'status' => 'Status',
        'amount' => 'Amount',
        'quantity' => 'Quantity',
        'trial_ends_at' => 'Trial Ends',
        'ends_at' => 'Ends At',
        'next_billing' => 'Next Billing',
        'created_at' => 'Created',
    ],

    'status' => [
        'active' => 'Active',
        'on_trial' => 'On Trial',
        'past_due' => 'Past Due',
        'canceled' => 'Canceled',
        'grace_period' => 'Grace Period',
        'paused' => 'Paused',
        'incomplete' => 'Incomplete',
        'expired' => 'Expired',
    ],

    'cycle' => [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'yearly' => 'Yearly',
    ],

    'tabs' => [
        'all' => 'All',
        'active' => 'Active',
        'issues' => 'Needs Attention',
    ],

    'filters' => [
        'gateway' => 'Gateway',
        'status' => 'Status',
        'plan' => 'Plan',
    ],

    'actions' => [
        'cancel' => 'Cancel',
        'cancel_heading' => 'Cancel :gateway Subscription',
        'cancel_description' => 'This will cancel the subscription. The customer will retain access until the end of their current billing period.',
        'cancel_success' => 'Subscription canceled successfully.',

        'cancel_immediately' => 'Cancel Immediately',
        'cancel_immediately_heading' => 'Cancel Subscription Immediately',
        'cancel_immediately_description' => 'This will immediately cancel the subscription. The customer will lose access right away.',
        'cancel_immediately_success' => 'Subscription canceled immediately.',

        'resume' => 'Resume',
        'resume_success' => 'Subscription resumed successfully.',

        'swap' => 'Change Plan',
        'swap_heading' => 'Change Subscription Plan',
        'swap_plan_label' => 'New Plan',
        'swap_prorate_label' => 'Prorate charges',
        'swap_success' => 'Plan changed successfully.',

        'view_external' => 'View in :gateway Dashboard',
    ],

    'create' => [
        'title' => 'Create Subscription',
        'steps' => [
            'customer' => 'Customer',
            'gateway' => 'Gateway',
            'plan' => 'Plan',
            'payment' => 'Payment',
        ],
        'customer_label' => 'Customer',
        'gateway_label' => 'Payment Gateway',
        'gateway_stripe_description' => 'Credit cards, ACH, international payments',
        'gateway_chip_description' => 'FPX, e-wallets, Malaysian payments',
        'plan_label' => 'Select Plan',
        'quantity_label' => 'Quantity (Seats)',
        'has_trial_label' => 'Include Trial Period',
        'trial_days_label' => 'Trial Days',
        'payment_method_label' => 'Payment Method',
        'payment_method_placeholder' => 'Use default or add new',
        'success' => 'Subscription created on :gateway.',
    ],

    'details' => [
        'title' => 'Subscription Details',
        'overview' => 'Overview',
        'gateway_details' => 'Gateway Details (:gateway)',
        'billing_info' => 'Billing Information',

        'subscription_id' => 'Subscription ID',
        'customer_id' => 'Customer ID',
        'price_id' => 'Price ID',
        'current_period' => 'Current Period',
        'collection_method' => 'Collection Method',
        'default_payment' => 'Default Payment Method',
        'schedule_id' => 'Schedule ID',
        'payment_token' => 'Payment Token',
        'next_charge' => 'Next Charge Date',
    ],

    'bulk' => [
        'cancel' => 'Cancel Selected',
        'cancel_confirm' => 'This will cancel: :summary',
        'export' => 'Export to CSV',
    ],

    'empty' => [
        'title' => 'No subscriptions yet',
        'description' => 'Create a subscription to get started.',
    ],
];
