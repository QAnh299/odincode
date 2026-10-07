<?php

declare(strict_types=1);

// Voucher pages: list (livewire/vouchers/index) and detail (livewire/vouchers/show)
return [
    'title'              => 'Vouchers',
    'subtitle'           => 'Look up discount codes, validity periods and usage',
    'detail_title'       => 'Voucher :code',

    // Lookup
    'lookup'             => 'Voucher lookup',
    'search'             => 'Search',
    'search_placeholder' => 'Search by voucher code or discount value (e.g. VCH001, 10, 500000)…',
    'all'                => 'All',
    'discount_type'      => 'Discount type',
    'valid_from'         => 'Valid from',
    'valid_to'           => 'Valid to',
    'sort_by'            => 'Sort by',
    'reset'              => 'Clear filters',
    'loading'            => 'Loading…',
    'result_count'       => ':count voucher(s) found',
    'empty'              => 'No vouchers found',
    'empty_sub'          => 'Try another keyword or remove some filters.',

    'sort' => [
        'newest' => 'Most recently started',
        'oldest' => 'Earliest start',
        'ending' => 'Ending soonest',
        'code'   => 'Voucher code (A → Z)',
    ],

    'type' => [
        'percentage' => 'Percentage off',
        'fixed'      => 'Fixed amount off',
    ],

    'state' => [
        'all'      => 'All vouchers',
        'active'   => 'Active',
        'inactive' => 'Inactive',
    ],

    // Table columns
    'code'               => 'Voucher code',
    'value'              => 'Discount',
    'validity'           => 'Validity period',
    'status'             => 'Status',
    'used'               => 'Quotations applied',
    'view'               => 'View details',
    'progress'           => ':percent% of the validity period has passed',

    // Detail
    'back'               => 'Back',
    'copy'               => 'Copy code',
    'copied'             => 'Copied',
    'caption_percentage' => 'off the quotation total',
    'caption_fixed'      => 'deducted from the quotation',
    'start_date'         => 'Start date',
    'end_date'           => 'End date',
    'today'              => 'Today',
    'total_days'         => ':days days in total',
    'days_left'          => ':days days remaining',
    'ends_today'         => 'Ends today',
    'starts_in'          => 'Starts in :days days',
    'ended_ago'          => 'Ended :days days ago',
    'subtotal_sum'       => 'Total before discount',
    'discount_sum'       => 'Total discounted',

    'quotations'         => 'Quotations using this voucher',
    'quotations_sub'     => 'All quotations in the system that use this voucher',
    'scope_note'         => 'Only quotations within your scope are shown',
    'no_quotations'      => 'No quotation has used this voucher yet',
    'quotation_id'       => 'Quotation',
    'customer'           => 'Customer',
    'employee'           => 'Employee',
    'created_at'         => 'Created',
    'subtotal'           => 'Before discount',
    'discount'           => 'Discount',

    'quotation_status' => [
        'Draft'     => 'Draft',
        'Sent'      => 'Sent',
        'Confirmed' => 'Confirmed',
        'Rejected'  => 'Rejected',
        'Expired'   => 'Expired',
        'Cancelled' => 'Cancelled',
    ],
];
