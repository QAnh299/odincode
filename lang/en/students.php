<?php

declare(strict_types=1);

// Student pages: list (livewire/students/index) and detail (livewire/students/show)
return [
    'title'              => 'Students',
    'subtitle'           => 'Customers who have paid the full tuition of their registered course',
    'detail_title'       => 'Student :code',

    'stat' => [
        'all'       => 'Total students',
        'Studying'  => 'Studying',
        'Completed' => 'Completed',
        'Dropped'   => 'Dropped',
    ],
    'status_name' => [
        'Studying'  => 'Studying',
        'Completed' => 'Completed',
        'Dropped'   => 'Dropped',
    ],

    // Lookup
    'lookup'             => 'Student lookup',
    'search'             => 'Search',
    'search_placeholder' => 'Search by code, name, phone or email…',
    'all'                => 'All',
    'all_courses'        => 'All courses',
    'team'               => 'Team',
    'all_teams'          => 'All teams',
    'salesperson'        => 'Salesperson',
    'all_salespeople'    => 'All salespeople',
    'choose_team_first'  => 'Choose a team first',
    'resigned'           => 'resigned',
    'from'               => 'From',
    'to'                 => 'To',
    'reset'              => 'Clear filters',
    'loading'            => 'Loading…',
    'result_count'       => ':count student(s) found',
    'empty'              => 'No students found',
    'empty_sub'          => 'Try another keyword or remove some filters.',

    // Columns / fields
    'code'               => 'Student code',
    'student'            => 'Student',
    'full_name'          => 'Full name',
    'phone'              => 'Phone',
    'email'              => 'Email',
    'date_of_birth'      => 'Date of birth',
    'course'             => 'Course',
    'conversion_date'    => 'Became student on',
    'owner'              => 'Owner',
    'status'             => 'Status',
    'actions'            => 'Actions',
    'view'               => 'View details',

    // Detail
    'back'               => 'Back to list',
    'edit'               => 'Edit',
    'info'               => 'Student information',
    'branch'             => 'Branch',
    'origin'             => 'Origin',
    'opportunity'        => 'Opportunity',
    'lead'               => 'Lead',
    'other_enrollments'  => 'Other enrollments',
    'courses_title'      => 'Registered courses',
    'no_order'           => 'No fully paid order found for this student.',
    'duration'           => 'Duration',
    'unit_price'         => 'Tuition',
    'quantity'           => 'Qty',
    'line_total'         => 'Amount',
    'payment_title'      => 'Payment',
    'order'              => 'Order',
    'payment_type'       => 'Payment type',
    'total'              => 'Total',
    'paid'               => 'Paid',
    'quotation'          => 'Quotation',
    'voucher'            => 'Voucher :code',
    'installment'        => 'Installment',
    'installment_no'     => 'Installment :no',
    'invoice'            => 'Invoice',
    'amount'             => 'Amount',
    'method'             => 'Method',
    'payment_date'       => 'Paid on',

    // Edit
    'edit_title'         => 'Edit student information',
    'save'               => 'Save changes',
    'saved'              => 'Student information updated.',
    'phone_invalid'      => 'The phone number must have 10 digits and start with 0.',
    'dob_invalid'        => 'The date of birth must be before today.',
];
