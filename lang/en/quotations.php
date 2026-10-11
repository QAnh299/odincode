<?php

declare(strict_types=1);

// Quotation page: list (livewire/quotations/index)
return [
    'title'              => 'Quotations',

    // Search
    'search'             => 'Search',
    'search_placeholder' => 'Search by quotation code, customer, phone, email, employee, course, voucher, order code…',
    'search_hint'        => 'Type several keywords to narrow results · "exact phrase" · -keyword to exclude',
    'search_help_title'  => 'How to search',
    'search_help'        => [
        'Several keywords: a quotation must match <strong>all</strong> of them, e.g. <code>nguyen ielts</code>',
        'Exact phrase: wrap it in double quotes, e.g. <code>"Bảo Châu"</code>',
        'Exclude: prefix with a minus sign, e.g. <code>-VCH001</code>',
        'Case and Vietnamese accents are ignored: <code>tieng anh</code> finds "Tiếng Anh"',
        'Searches: quotation code, status, notes, opportunity code, customer name/phone/email, creator, order code, voucher code, courses',
    ],

    // Filters
    'filters'            => 'Filters',
    'close'              => 'Close',
    'active_filters'     => 'Filtering by',
    'remove_filter'      => 'Remove filter',
    'all'                => 'All',
    'all_employees'      => 'All employees',
    'all_teams'          => 'All teams',
    'status_label'       => 'Status',
    'deadline_label'     => 'Deadline',
    'team'               => 'Sales team',
    'created_range'      => 'Created date',
    'created_from'       => 'Created from',
    'created_to'         => 'Created to',
    'expiry_range'       => 'Expiry date',
    'expiry_from'        => 'Expires from',
    'expiry_to'          => 'Expires to',
    'invalid_range'      => 'End date must be on or after the start date',
    'sort_by'            => 'Sort by',
    'reset'              => 'Clear filters',
    'reset_all'          => 'Clear all filters',
    'reset_form'         => 'Reset',
    'apply'              => 'Apply',
    'pending'            => 'Filters changed, click Apply to update the list',
    'loading'            => 'Loading…',
    'result_count'       => ':count quotation found|:count quotations found',
    'empty'              => 'No matching quotations',
    'empty_sub'          => 'Try another keyword or remove some filters.',
    'empty_none'         => 'No quotations yet',
    'empty_none_sub'     => 'Quotations are created from opportunities at the Closed stage.',

    'sort' => [
        'newest'     => 'Most recently created',
        'oldest'     => 'Earliest created',
        'expiring'   => 'Expiring soonest',
        'code'       => 'Quotation code (A → Z)',
        'total_desc' => 'Total high → low',
        'total_asc'  => 'Total low → high',
        'customer'   => 'Customer name (A → Z)',
    ],

    // Quotation status (Quotation::STATUSES)
    'status' => [
        'Draft'     => 'Draft',
        'Confirmed' => 'Confirmed',
        'Rejected'  => 'Rejected',
    ],

    // Deadline state (draft quotations only)
    'deadline' => [
        'valid'   => 'Not expired',
        'soon'    => 'Expiring soon (≤ 3 days)',
        'overdue' => 'Overdue',
    ],

    'order_state' => [
        'with'    => 'Has an order',
        'without' => 'No order yet',
    ],

    // Group labels on active filter chips
    'chip' => [
        'search'   => 'Keywords',
        'statuses' => 'Status',
        'deadline' => 'Deadline',
        'order'    => 'Order',
        'created'  => 'Created',
        'expiry'   => 'Expires',
        'team'     => 'Team',
        'employee' => 'Employee',
        'range'    => ':from – :to',
        'from'     => 'from :value',
        'to'       => 'to :value',
    ],

    // ── Detail page (livewire/quotations/show) ──
    'detail_title'       => 'Quotation :code',

    'detail' => [
        'back'              => 'Back to list',
        'expires_on'        => 'Expires :date',
        // Action bar (steps 7, 10, 12, 16) – not functional yet
        'actions'           => 'Actions',
        'edit'              => 'Edit',
        'export_pdf'        => 'Export PDF',
        'confirm'           => 'Confirm quotation',
        'reject'            => 'Reject quotation',
        'coming_soon'       => 'This feature will be available in a later step',
        'expired_warning'   => 'This quotation has expired; it cannot be exported to PDF or confirmed',
        // Progress
        'progress'          => 'Progress',
        'step_quotation'    => 'Quotation',
        'step_order'        => 'Order',
        'step_invoice'      => 'Invoice',
        'step_payment'      => 'Payment',
        'none_yet'          => 'None yet',
        'invoice_count'     => ':count invoice|:count invoices',
        'paid_of'           => ':paid / :total',
        'rejected_note'     => 'The quotation was rejected, so no order is created',
        // General information
        'general'           => 'General information',
        'created_at'        => 'Created',
        'updated_at'        => 'Last updated',
        'expiry_date'       => 'Expiry date',
        'employee'          => 'Created by',
        'notes'             => 'Notes',
        'no_notes'          => 'No notes',
        // Customer, opportunity
        'customer'          => 'Customer',
        'full_name'         => 'Full name',
        'email'             => 'Email',
        'phone'             => 'Phone',
        'opportunity'       => 'Opportunity',
        'opportunity_id'    => 'Opportunity code',
        'stage'             => 'Stage',
        'opportunity_state' => 'Opportunity status',
        // Courses + totals
        'courses'           => 'Courses in quotation',
        'no_courses'        => 'This quotation has no courses',
        'no'                => '#',
        'course_id'         => 'Code',
        'course_name'       => 'Course name',
        'unit_price'        => 'Unit price',
        'quantity'          => 'Qty',
        'vat'               => 'VAT',
        'line_total'        => 'Amount',
        'subtotal'          => 'Subtotal',
        'discount'          => 'Voucher :code (−:value)',
        'grand_total'       => 'Total',
        // Voucher
        'voucher'           => 'Applied voucher',
        'voucher_code'      => 'Voucher code',
        'voucher_type'      => 'Discount type',
        'voucher_value'     => 'Value',
        'voucher_validity'  => 'Validity period',
        'voucher_invalid'   => 'This voucher is no longer valid; the discount is still calculated as when the quotation was created',
        // Order
        'order'             => 'Order',
        'order_id'          => 'Order code',
        'order_created'     => 'Order created',
        'order_status'      => 'Order status',
        'payment_type'      => 'Payment type',
        'order_total'       => 'Order total',
        'paid'              => 'Paid',
        'remaining'         => 'Remaining',
        'invoices'          => 'Invoices',
    ],

    // Opportunity status (Opportunity::STATUSES)
    'opportunity_status' => [
        'InProgress' => 'In progress',
        'Won'        => 'Won',
        'Lost'       => 'Lost',
        'Archived'   => 'Archived',
    ],

    // Order status (SalesOrders.status)
    'order_status' => [
        'InProgress' => 'In progress',
        'Completed'  => 'Completed',
    ],

    // Payment type (SalesOrders.payment_type)
    'payment_type' => [
        'Full'        => 'Full payment',
        'Installment' => 'Installments',
        'none'        => 'Not decided',
    ],

    // Table columns
    'code'               => 'Quotation',
    'customer'           => 'Customer',
    'employee'           => 'Created by',
    'created_at'         => 'Created',
    'expiry_date'        => 'Expiry date',
    'total'              => 'Total',
    'order'              => 'Order',
    'days_left'          => ':days day(s) left',
    'overdue'            => 'Overdue',
];
