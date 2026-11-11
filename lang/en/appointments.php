<?php

declare(strict_types=1);

// Salesperson appointment list (livewire/appointments/index) + appointment reminders in the notification bell
return [
    'title'              => 'Appointments',
    'subtitle'           => 'Placement test / consultation appointments you are handling',

    'stat' => [
        'total'     => 'Total appointments',
        'Scheduled' => 'Scheduled',
        'Success'   => 'Attended',
        'Failed'    => 'No-show',
    ],

    'filters'            => 'Filter appointments',
    'search_placeholder' => 'Search by customer name, phone, opportunity code',
    'all_statuses'       => 'All statuses',
    'all_periods'        => 'All time',
    'period' => [
        'today'    => 'Today',
        'tomorrow' => 'Tomorrow',
        'week'     => 'Next 7 days',
        'month'    => 'This month',
        'past'     => 'Past appointment time',
    ],
    'reset'              => 'Clear filters',
    'loading'            => 'Loading…',
    'empty'              => 'No appointments',
    'empty_sub'          => 'Try another status, time range or search term.',
    'showing'            => 'Showing :shown of :total appointments',

    'customer'           => 'Customer',
    'scheduled_time'     => 'Appointment time',
    'type'               => 'Type',
    'location'           => 'Location',
    'actions'            => 'Actions',
    'opportunity'        => 'Opportunity',
    'prev'               => 'Previous',
    'next'               => 'Next',
    'opportunity_ref'    => 'Opportunity :code',
    'open_detail'        => 'View appointment in opportunity details',
    'today'              => 'Today',
    'tomorrow'           => 'Tomorrow',

    'status' => [
        'Scheduled' => 'Scheduled',
        'Overdue'   => 'Awaiting result',
        'Success'   => 'Attended',
        'Failed'    => 'No-show',
        'Cancelled' => 'Cancelled',
    ],
    'badge' => [
        'Scheduled' => 'Scheduled',
        'Overdue'   => 'Awaiting result',
        'Success'   => 'Attended',
        'Failed'    => 'No-show',
        'Cancelled' => 'Cancelled',
    ],

    // Action popups
    'action' => [
        'reschedule' => 'Reschedule',
        'result'     => 'Update result',
        'cancel'     => 'Cancel appointment',
        'wait'       => 'Result can be updated from the appointment time',
    ],
    'dialog' => [
        'result_title'     => 'Update appointment result',
        'reschedule_title' => 'Reschedule appointment',
        'cancel_title'     => 'Cancel appointment',
        'outcome'          => 'Outcome',
        'attended'         => 'Customer attended',
        'attended_hint'    => 'Record the session result',
        'absent'           => 'Customer did not show',
        'absent_hint'      => 'You can contact them to rebook later',
        'current_time'     => 'Current time',
        'new_time'         => 'New time',
        'cancel_warning'   => 'If the customer has no pending appointment and has not attended any, the opportunity moves to Archived (Lost).',
        'save_result'      => 'Save result',
        'save_reschedule'  => 'Save new time',
        'save_cancel'      => 'Confirm cancellation',
        'close'            => 'Close',
        'back'             => 'Back',
    ],
    'rescheduled'        => 'Appointment moved to :time.',
    'cannot_change'      => 'Cannot reschedule / cancel: the appointment time has passed, it already has a result, or the opportunity has reached the Closing stage.',

    // Notification bell
    'reminder_title'     => ':types appointment with :name',
    'overdue_title'      => 'Update the :types appointment result with :name',
    'overdue_time'       => 'Past appointment time :time',
    'reminder_header'    => 'Appointments today & tomorrow - remind your customers',
    'view_all'           => 'View all appointments',
];
