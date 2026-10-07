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

    // Table columns / detail fields
    'code'               => 'Course code',
    'name'               => 'Course name',
    'description'        => 'Description',
    'price'              => 'Tuition',
    'vat'                => 'VAT',
    'duration'           => 'Duration',
    'status'             => 'Status',
    'created_at'         => 'Created at',
    'actions'            => 'Actions',
    'view'               => 'View details',

    // Detail
    'back'               => 'Back',
    'info'               => 'Course information',
    'read_only'          => 'View only, course information cannot be edited',
    'no_description'     => 'No description',

    // Business overview
    'overview'           => 'Business overview',
    'overview_sub'       => 'Aggregated from all orders (excluding cancelled) and paid invoices that include this course',
    'scope_note'         => 'Only orders within your visibility scope are counted',
    'registrations'      => 'Registrations',
    'revenue'            => 'Revenue',
    'paid'               => 'Collected',
    'remaining'          => 'Outstanding',
    'students'           => 'Students',
    'collected_rate'     => 'Collection rate',

    // Business history
    'history'            => 'Business history',
    'history_sub'        => 'Grouped by order month; collected / outstanding as of today',
    'from_month'         => 'From month',
    'to_month'           => 'To month',
    'default_period'     => 'Last 12 months',
    'period_note'        => 'Showing :from – :to (:count months)',
    'month'              => 'Month',
    'month_label'        => ':month',
    'total'              => 'Total',
    'no_sales'           => 'No orders in this period',

    // Charts
    'chart_revenue'       => 'Revenue by month',
    'chart_registrations' => 'Registrations by month',
    'registration_count'  => '{1} :count registration|[0,*] :count registrations',
    'unit_thousand'       => 'K',
    'unit_million'        => 'M',
    'unit_billion'        => 'B',
];
