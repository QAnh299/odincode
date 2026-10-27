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
        'range'    => ':from - :to',
        'from'     => 'from :value',
        'to'       => 'to :value',
    ],

    // ── Create / edit quotation (livewire/quotations/form) ──
    'form' => [
        'create_title'     => 'New quotation',
        'edit_title'       => 'Edit quotation :code',
        'back_opportunity' => 'Back to opportunity',
        'back_quotation'   => 'Back to quotation',
        'blocked_title'    => 'Cannot create a quotation',
        // Step 2 not met
        'blocked' => [
            'not_owner'     => 'Only the salesperson in charge of this opportunity can create a quotation.',
            'not_closed'    => 'The opportunity is not at the Closed stage yet.',
            'closed_status' => 'The opportunity is Lost or Archived, so no quotation can be created.',
            'has_draft'     => 'The opportunity already has a draft quotation. Edit, confirm or reject it first.',
        ],
        'courses'          => 'Courses',
        'course'           => 'course',
        'pick_course'      => 'Pick an active course…',
        'all_courses_added' => 'All active courses have been added',
        'no_lines'         => 'No courses yet. Pick a course above to add it to the quotation.',
        'remove'           => 'Remove course',
        'quantity'         => 'quantity',
        'expiry_date'      => 'Expiry date',
        'expiry_help'      => 'Defaults to :days days after the creation date; cannot be before it.',
        'voucher'          => 'Voucher',
        'no_voucher'       => 'No voucher',
        'voucher_until'    => 'until :date',
        'voucher_help'     => 'Only active vouchers within their validity period are listed.',
        'customer_email'      => 'Customer email',
        'customer_email_help' => 'The customer has no email yet. Add it so the invoice can be emailed after payment.',
        'notes'            => 'Notes',
        'created_date'     => 'Created',
        'cancel'           => 'Cancel',
        'save_draft'       => 'Save draft quotation',
        'save_changes'     => 'Save changes',
        'created'          => 'Quotation :code created (Draft). Export the PDF to send it to the customer.',
        'updated'          => 'Quotation :code saved. Export the PDF again to send the new version.',
    ],

    // Draft quotations awaiting a reply, in the salesperson's notification bell (step 11)
    'reminder' => [
        'header'   => 'Appointments & quotations to follow up today and tomorrow',
        'today'    => 'Quotation :code · :name expires today',
        'tomorrow' => 'Quotation :code · :name expires tomorrow',
        'overdue'  => 'Quotation :code · :name expired :days days ago: extend or reject it',
        'time'     => 'Expires :date · contact the customer',
    ],


    // ── Quotation PDF (pdf/quotation) ──
    'pdf' => [
        'title'           => 'Quotation',
        'company'         => 'ODIN Language Academy',
        'number'          => 'No.',
        'date'            => 'Date',
        'valid_until'     => 'Valid until',
        'customer'        => 'Customer',
        'consultant'      => 'Consultant',
        'footer'          => 'This quotation is valid until the end of :date. Fees include VAT where applicable.',
        'customer_sign'   => 'Customer',
        'consultant_sign' => 'Consultant',
    ],

    // ── Detail page (livewire/quotations/show) ──
    'detail_title'       => 'Quotation :code',

    'detail' => [
        'back'              => 'Back to list',
        'back_opportunity'  => 'Back to opportunity :code',
        'expires_on'        => 'Expires :date',
        // Action bar (steps 7, 10, 12, 16)
        'actions'           => 'Actions',
        'edit'              => 'Edit',
        'export_pdf'        => 'Export PDF',
        'confirm'           => 'Confirm quotation',
        'reject'            => 'Reject quotation',
        'coming_soon'       => 'This feature will be available in a later step',
        'confirm_hint'      => 'Click Confirm when the customer agrees: the system creates the order, then you create an invoice.',
        'confirm_confirm'   => 'Has the customer accepted quotation :code? Confirming creates the order; the quotation can no longer be edited or rejected.',
        'confirmed_done'    => 'Quotation :code confirmed and order :order created. Click "Create invoice" in the Order block to bill the customer.',
        // Step 17 not met
        'confirm_blocked'   => [
            'not_author'      => 'Only the salesperson who created the quotation can confirm it.',
            'not_draft'       => 'The quotation is no longer a draft.',
            'expired'         => 'The quotation has expired. Extend it before confirming.',
            'has_order'       => 'The quotation already has an order.',
            'no_lines'        => 'The quotation has no courses.',
        ],
        'rejected_done'     => 'Quotation :code rejected.',
        'expired_warning'   => 'This quotation has expired. If the customer still wants it, choose Edit to extend the expiry date, then confirm; otherwise choose Reject.',
        // Step 14: after rejecting
        'close_reason'      => 'Customer did not accept quotation :code.',
        'close_reason_no_response' => 'Customer did not respond to quotation :code before it expired.',
        'opportunity_lost'  => 'The opportunity is now Lost.',
        'opportunity_archived' => 'The opportunity is now Archived.',
        'reject_reason_title' => 'Why is quotation :code being rejected?',
        'reject_reason' => [
            'declined'    => 'Customer did not accept',
            'no_response' => 'Customer did not respond before the quotation expired',
        ],
        'reject_reason_hint' => [
            'declined'    => 'An in-progress opportunity becomes Lost.',
            'no_response' => 'An in-progress opportunity becomes Archived, like an unreachable customer.',
        ],
        'reject_cancel' => 'Cancel',
        'reject_submit' => 'Reject quotation',
        'opportunity_kept'  => 'The opportunity is already Won, so it stays Won.',
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
        'create_invoice'    => 'Create invoice',
        'create_next_invoice' => 'Create next invoice',
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
