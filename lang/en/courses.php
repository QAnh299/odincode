<?php

declare(strict_types=1);

// Course pages: list (livewire/courses/index) and detail (livewire/courses/show)
return [
    'title'              => 'Courses',
    'subtitle'           => 'Look up course information, tuition and availability',
    'detail_title'       => 'Course :code',

    // Lookup
    'lookup'             => 'Course lookup',
    'search'             => 'Search',
    'search_placeholder' => 'Search by course code or name (e.g. CRS001, IELTS)…',
    'all'                => 'All',
    'reset'              => 'Clear filters',
    'loading'            => 'Loading…',
    'result_count'       => ':count course(s) found',
    'empty'              => 'No courses found',
    'empty_sub'          => 'Try another keyword or remove some filters.',

    'state' => [
        'all'      => 'All courses',
        'active'   => 'Offered',
        'inactive' => 'Discontinued',
    ],

    // Table columns / fields
    'code'               => 'Course code',
    'name'               => 'Course name',
    'price'              => 'Tuition',
    'vat'                => 'VAT',
    'duration'           => 'Duration',
    'status'             => 'Status',
    'actions'            => 'Actions',
    'view'               => 'View details',

    // Detail: course content
    'back_to_list'       => 'Back to list',
    'content'            => 'Course content',
    'overview_heading'   => 'Overview',
    'no_description'     => 'No description yet',
    'created_on'         => 'Course created on :date',

    // Detail: statistics
    'stats'              => 'Statistics',
    'quotations_created' => 'Quotations created',
    'seats_confirmed'    => 'Seats confirmed',
    'close_rate'         => 'Close rate',
    'close_rate_hint'    => 'Confirmed ÷ (Confirmed + Rejected)',
    'revenue_confirmed'  => 'Confirmed revenue',
    'before_voucher'     => 'Before vouchers',
    'scope_own'          => 'Only quotations you created are counted',
    'scope_team'         => 'Only quotations from team :team are counted',
    'scope_employee'     => 'Only quotations from :name (:team) are counted',

    // Detail: scope filter (Sale Admin, Sale Leader)
    'scope_filter'       => 'Viewing scope',
    'team'               => 'Team',
    'all_teams'          => 'All teams',
    'no_team'            => 'Not in a team',
    'employee'           => 'Employee',
    'all_employees'      => 'All',
    'choose_team_first'  => 'Choose a team first',
    'resigned'           => 'resigned',
    'clear_scope'        => 'Clear',

    // Detail: quotations by status
    'by_status'          => 'Quotations by status',
    'filter_by_status'   => 'Filter the quotation list by status',

    'quotation_status' => [
        'Draft'     => 'Draft',
        'Confirmed' => 'Confirmed',
        'Rejected'  => 'Rejected',
    ],

    // Detail: quotation list
    'quotations_title'     => 'Quotations with this course',
    'quotation_id'         => 'Quotation ID',
    'quotation_created_at' => 'Created at',
    'quantity'             => 'Quantity',
    'no_quotations'        => 'No quotations yet',
    'no_quotations_status' => 'No quotations with this status',
    'clear_filter'         => 'Show all quotations',
];
