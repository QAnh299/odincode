<?php

declare(strict_types=1);

// Order page: list (livewire/orders/index)
return [
    'title'              => 'Orders',
    'subtitle'           => 'Look up orders created from confirmed quotations: totals, payment progress and invoices of each order.',

    // Search
    'search'             => 'Search',
    'search_placeholder' => 'Search by order code, customer, phone, email, quotation, employee, course, voucher, invoice code, amount…',
    'search_hint'        => 'Several keywords · "exact phrase" · -exclude',
    'search_help_title'  => 'How to search',
    'search_help'        => [
        'Several keywords: an order must match <strong>all</strong> of them, e.g. <code>nguyen installment</code>',
        'Exact phrase: wrap it in double quotes, e.g. <code>"Bảo Châu"</code>',
        'Exclude: prefix with a minus sign, e.g. <code>-ORD001</code>',
        'Case and Vietnamese accents are ignored: <code>toan bo</code> finds "Toàn bộ"',
        'Amount: type the full number, e.g. <code>5400000</code> or <code>5.400.000</code> (compared with total, paid, remaining)',
        'Searches: order code, status, payment type, notes, quotation / opportunity / voucher / invoice code, customer name/phone/email, employee, courses, amount',
    ],

    // Status cards
    'all'                => 'All',

    // Filters
    'filters'            => 'Filters',
    'close'              => 'Close',
    'active_filters'     => 'Filtering by',
    'remove_filter'      => 'Remove filter',
    'all_employees'      => 'All employees',
    'all_teams'          => 'All teams',
    'status_label'       => 'Status',
    'payment_type_label' => 'Payment type',
    'progress_label'     => 'Payment progress',
    'invoice_label'      => 'Invoices',
    'amount_range'       => 'Total (VND)',
    'amount_from'        => 'Total from',
    'amount_to'          => 'Total to',
    'created_range'      => 'Order date',
    'created_from'       => 'Created from',
    'created_to'         => 'Created to',
    'team'               => 'Sales team',
    'employee'           => 'Employee',
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
    'result_count'       => ':count order found|:count orders found',
    'empty'              => 'No matching orders',
    'empty_sub'          => 'Try another keyword or remove some filters.',
    'empty_none'         => 'No orders yet',
    'empty_none_sub'     => 'Orders are created when a quotation is confirmed.',

    'sort' => [
        'newest'         => 'Newest created',
        'oldest'         => 'Oldest created',
        'remaining_desc' => 'Remaining high → low',
        'total_desc'     => 'Total high → low',
        'total_asc'      => 'Total low → high',
        'code'           => 'Order code (A → Z)',
        'customer'       => 'Customer name (A → Z)',
    ],

    // Order status (SalesOrder::STATUSES)
    'status' => [
        'InProgress' => 'In progress',
        'Completed'  => 'Completed',
    ],

    // Payment type (SalesOrder::PAYMENT_TYPES; none = NULL or empty)
    'payment_type' => [
        'Full'        => 'Full payment',
        'Installment' => 'Installments',
        'none'        => 'Not decided',
    ],

    // Payment progress
    'progress' => [
        'unpaid'  => 'Not paid',
        'partial' => 'Partially paid',
        'paid'    => 'Fully paid',
    ],

    // Invoice filter
    'invoice_state' => [
        'pending'       => 'Has invoices awaiting payment',
        'overdue'       => 'Has overdue invoices',
        'none'          => 'No invoices yet',
        'needs_invoice' => 'Next invoice needed',
    ],

    // Group labels on active filter chips
    'chip' => [
        'search'   => 'Keywords',
        'statuses' => 'Status',
        'payment'  => 'Payment type',
        'progress' => 'Progress',
        'invoice'  => 'Invoices',
        'amount'   => 'Total',
        'created'  => 'Created',
        'team'     => 'Team',
        'employee' => 'Employee',
        'range'    => ':from - :to',
        'from'     => 'from :value',
        'to'       => 'to :value',
    ],

    // Table columns
    'code'               => 'Order',
    'customer'           => 'Customer',
    'quotation'          => 'Quotation',
    'payment_type_col'   => 'Payment type',
    'total'              => 'Total',
    'paid'               => 'Paid',
    'remaining'          => 'Remaining',
    'invoices'           => 'Invoices',
    'invoice_count'      => ':count invoice|:count invoices',
    'invoices_pending'   => ':count awaiting payment',
    'invoices_overdue'   => ':count overdue',
    'view_invoices'      => 'View invoices of order :code',
    'create_invoice'     => 'Create invoice',
    'view_quotation'     => 'View quotation :code',

    // Order detail page
    // ── Khung phiếu ở chi tiết đơn hàng (components/invoice-receipt, type = order) ──
    'doc' => [
        'title'  => 'SALES ORDER',
        'number' => 'Order no.',
    ],
    'detail' => [
        'title'               => 'Order :code',
        'back'                => 'Order list',
        'actions'             => 'Actions',
        'created_on'          => 'Created :date',
        'create_next_invoice' => 'Create next invoice',
        'general'             => 'Order information',
        'created_at'          => 'Created at',
        'invoiced'            => 'Invoiced',
        'payment'             => 'Payment status',
        'notes'               => 'Notes',
        'no_notes'            => 'No notes',
        'courses'             => 'Courses (from the confirmed quotation)',
        'no_courses'          => 'The quotation of this order has no courses.',
        'no'                  => 'No.',
        'course_id'           => 'Course code',
        'course_name'         => 'Course name',
        'unit_price'          => 'Unit price',
        'quantity'            => 'Quantity',
        'vat'                 => 'VAT',
        'line_total'          => 'Line total',
        'subtotal'            => 'Subtotal',
        'discount'            => 'Voucher discount :code',
        'installments'        => 'Invoice history',
        'no_invoices'         => 'This order has no invoices yet.',
        'installment'         => 'Installment',
        'invoice_code'        => 'Invoice code',
        'method'              => 'Method',
        'amount'              => 'Amount',
        'issued'              => 'Issued',
        'due'                 => 'Due date',
        'paid_at'             => 'Paid at',
        'customer'            => 'Customer',
        'full_name'           => 'Full name',
        'email'               => 'Email',
        'phone'               => 'Phone',
        'opportunity'         => 'Opportunity',
        'view'                => 'View order :code',
    ],
];
