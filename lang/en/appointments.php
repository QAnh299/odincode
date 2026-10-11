<?php

declare(strict_types=1);

// Salesperson appointments page (livewire/appointments/index) + appointment reminders in the notification bell
return [
    'title'              => 'Appointments',
    'subtitle'           => 'Placement test / consultation appointments you are handling',

    'stat' => [
        'today'    => 'Today',
        'tomorrow' => 'Tomorrow',
        'week'     => 'Next 7 days',
        'overdue'  => 'Awaiting result',
    ],

    'filters'            => 'Filter appointments',
    'view_label'         => 'Show',
    'view' => [
        'upcoming' => 'Upcoming',
        'today'    => 'Today',
        'tomorrow' => 'Tomorrow',
        'week'     => 'Next 7 days',
        'overdue'  => 'Awaiting result',
        'done'     => 'With result',
        'all'      => 'All',
    ],
    'search'             => 'Search',
    'search_placeholder' => 'Search by customer name, phone, appointment or opportunity code…',
    'all_types'          => 'All types',
    'reset'              => 'Clear filters',
    'loading'            => 'Loading…',
    'result_count'       => ':count appointment(s)',
    'empty'              => 'No appointments',
    'empty_sub'          => 'Try another view or remove some filters.',

    'time'               => 'Time',
    'customer'           => 'Customer',
    'opportunity'        => 'Opportunity',
    'actions'            => 'Actions',
    'view_detail'        => 'View',
    'today'              => 'Today',
    'tomorrow'           => 'Tomorrow',

    'status' => [
        'Scheduled' => 'Scheduled',
        'Overdue'   => 'Awaiting result',
        'Success'   => 'Attended',
        'Failed'    => 'No-show',
    ],

    // Notification bell
    'reminder_title'     => ':types appointment with :name',
    'reminder_header'    => 'Appointments today & tomorrow – remind your customers',
    'view_all'           => 'View all appointments',
];
