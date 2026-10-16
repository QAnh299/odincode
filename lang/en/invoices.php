<?php

declare(strict_types=1);

// Invoice page: list (livewire/invoices/index)
return [
    'title'              => 'Invoices',
    'subtitle'           => 'Look up order invoices: amount, due date, payment method and payment status.',

    // Search
    'search'             => 'Search',
    'search_placeholder' => 'Search by invoice code, customer, phone, email, order, quotation, employee, accountant, course, amount…',
    'search_hint'        => 'Several keywords · "exact phrase" · -exclude',
    'search_help_title'  => 'How to search',
    'search_help'        => [
        'Several keywords: an invoice must match <strong>all</strong> of them, e.g. <code>nguyen installment</code>',
        'Exact phrase: wrap it in double quotes, e.g. <code>"Bảo Châu"</code>',
        'Exclude: prefix with a minus sign, e.g. <code>-INV001</code>',
        'Case and Vietnamese accents are ignored: <code>chuyen khoan</code> finds "Chuyển khoản"',
        'Amount: type the full number, e.g. <code>5400000</code> or <code>5.400.000</code>',
        'Searches: invoice code, status, method, payment type, order / quotation / opportunity code, customer name/phone/email, creator, accountant, courses, amount',
    ],

    // Status cards + totals
    'all'                => 'All',
    'collected'          => 'Collected',
    'outstanding'        => 'Outstanding',
    'totals_label'       => 'Totals for the current filters',

    // Filters
    'filters'            => 'Filters',
    'close'              => 'Close',
    'active_filters'     => 'Filtering by',
    'remove_filter'      => 'Remove filter',
    'all_employees'      => 'All employees',
    'all_teams'          => 'All teams',
    'status_label'       => 'Status',
    'method_label'       => 'Method',
    'deadline_label'     => 'Due state',
    'deadline_hint'      => 'Unpaid invoices only',
    'payment_type_label' => 'Payment type (order)',
    'amount_range'       => 'Amount (VND)',
    'amount_from'        => 'Amount from',
    'amount_to'          => 'Amount to',
    'issued_range'       => 'Issued date',
    'issued_from'        => 'Issued from',
    'issued_to'          => 'Issued to',
    'due_range'          => 'Due date',
    'due_from'           => 'Due from',
    'due_to'             => 'Due to',
    'paid_range'         => 'Payment date',
    'paid_from'          => 'Paid from',
    'paid_to'            => 'Paid to',
    'team'               => 'Sales team',
    'employee'           => 'Created by',
    'cash_pending'       => 'Cash awaiting accountant approval',
    'cash_pending_hint'  => 'Unpaid cash invoices (the accountant checks and approves them)',
    'invalid_amount'     => 'Amount must be a non-negative number',
    'invalid_amount_range' => 'End amount must be greater than or equal to the start amount',
    'invalid_range'      => 'End date must be on or after the start date',
    'sort_by'            => 'Sort by',
    'reset'              => 'Clear filters',
    'reset_all'          => 'Clear all filters',
    'reset_form'         => 'Reset',
    'apply'              => 'Apply',
    'pending'            => 'Filters changed, click Apply to update the list',
    'loading'            => 'Loading…',
    'result_count'       => ':count invoice found|:count invoices found',
    'empty'              => 'No matching invoices',
    'empty_sub'          => 'Try another keyword or remove some filters.',
    'empty_none'         => 'No invoices yet',
    'empty_none_sub'     => 'Invoices are created from orders after a quotation is confirmed.',

    'sort' => [
        'newest'      => 'Newest issued',
        'oldest'      => 'Oldest issued',
        'due'         => 'Nearest due date',
        'amount_desc' => 'Amount high → low',
        'amount_asc'  => 'Amount low → high',
        'code'        => 'Invoice code (A → Z)',
        'customer'    => 'Customer name (A → Z)',
    ],

    // Invoice status (Invoice::STATUSES)
    'status' => [
        'Unpaid'  => 'Awaiting payment',
        'Paid'    => 'Paid',
        'Overdue' => 'Overdue',
    ],

    // Payment method (Invoice::METHODS; none = not chosen)
    'method' => [
        'BankTransfer' => 'Bank transfer',
        'Cash'         => 'Cash',
        'none'         => 'Not chosen',
    ],

    // Order payment type (SalesOrder::PAYMENT_TYPES)
    'payment_type' => [
        'Full'        => 'Full payment',
        'Installment' => 'Installments',
    ],

    // Due state (unpaid invoices only)
    'deadline' => [
        'valid'   => 'Not due yet',
        'soon'    => 'Due soon (≤ 3 days)',
        'overdue' => 'Overdue',
    ],

    // Group labels on active filter chips
    'chip' => [
        'search'       => 'Keywords',
        'statuses'     => 'Status',
        'methods'      => 'Method',
        'deadline'     => 'Due',
        'payment_type' => 'Payment type',
        'amount'       => 'Amount',
        'issued'       => 'Issued',
        'due'          => 'Due date',
        'paid'         => 'Paid',
        'team'         => 'Team',
        'employee'     => 'Employee',
        'range'        => ':from - :to',
        'from'         => 'from :value',
        'to'           => 'to :value',
    ],

    // Table columns
    'code'               => 'Invoice',
    'installment'        => 'Installment :number',
    'customer'           => 'Customer',
    'order'              => 'Order',
    'amount'             => 'Amount',
    'issued_date'        => 'Issued',
    'due_date'           => 'Due date',
    'accountant'         => 'Accountant: :name',
    'days_left'          => ':days day(s) left',
    'days_late'          => ':days day(s) late',
];
