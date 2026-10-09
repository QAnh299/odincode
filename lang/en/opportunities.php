<?php

declare(strict_types=1);

// Opportunity Kanban page (livewire/opportunities/kanban)
return [
    'title'              => 'Opportunities',
    'subtitle'           => 'Opportunities you own, grouped by stage',

    'status'             => 'Status',
    'result_count'       => ':count opportunity(ies)',

    'empty_stage'        => 'No opportunities',
    'no_value'           => 'No expected value',
    'conversion_date'    => 'Conversion date',

    // Detail
    'detail_title'       => 'Opportunity :code',
    'back'               => 'Back to Kanban',
    'create_quotation'   => 'Create quotation',
    'stage'              => 'Stage',
    'customer'           => 'Customer',
    'email'              => 'Email',
    'lead'               => 'Source lead',
    'info'               => 'Opportunity info',
    'expected_value'     => 'Expected value',
    'student'            => 'Student',

    'care_history'       => 'Care history',
    'no_care'            => 'No care activities yet.',
    'care_result' => [
        'Success' => 'Success',
        'Failed'  => 'Failed',
    ],

    'appointments'       => 'Appointments',
    'no_appointments'    => 'No appointments yet.',
    'appointment_time'   => 'Time',
    'appointment_type'   => 'Type',
    'location'           => 'Location',
    'notes'              => 'Notes',
    'appointment_type_name' => [
        'Phone'        => 'Phone call',
        'Consultation' => 'In-person consultation',
        'Online'       => 'Online',
    ],
    'appointment_status' => [
        'Scheduled' => 'Scheduled',
        'Success'   => 'Success',
        'Failed'    => 'Failed',
    ],

    'quotations'           => 'Quotations',
    'no_quotations'        => 'No quotations yet. Quotations can only be created at the Closed stage.',
    'no_quotations_closed' => 'No quotations yet. Click "Create quotation" to prepare one for the customer.',
    'quotation_id'         => 'Quotation',
    'created_at'           => 'Created',
    'expiry_date'          => 'Valid until',
    'voucher'              => 'Voucher',
    'quotation_total'      => 'Total',
    'total_hint'           => 'Total is the sum of course lines (VAT included), before voucher discount.',

    'student_status' => [
        'Studying'  => 'Studying',
        'Dropped'   => 'Dropped',
        'Completed' => 'Completed',
    ],
];
