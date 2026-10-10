<?php

declare(strict_types=1);

// Employee pages: list (livewire/employees/index), detail (livewire/employees/show)
return [
    'title'              => 'Employees',
    'subtitle'           => 'Look up employee information, roles, sales teams and branches',
    'subtitle_team'      => 'Employees in team :team',
    'subtitle_no_team'   => 'You are not in any sales team yet',
    'detail_title'       => 'Employee :name',

    // Lookup
    'lookup'             => 'Employee lookup',
    'search'             => 'Search',
    'search_placeholder' => 'Search by code, name, email or phone (e.g. EMP004, Lan, 0901…)…',
    'all'                => 'All',
    'sort_by'            => 'Sort by',
    'reset'              => 'Clear filters',
    'loading'            => 'Loading…',
    'result_count'       => ':count employee(s) found',
    'empty'              => 'No employees found',
    'empty_sub'          => 'Try another keyword or remove some filters.',

    'sort' => [
        'code'   => 'Employee code (A → Z)',
        'name'   => 'Name (A → Z)',
        'newest' => 'Most recently hired',
        'oldest' => 'Longest serving',
    ],

    'state' => [
        'all'      => 'All employees',
        'working'  => 'Working',
        'resigned' => 'Resigned',
    ],

    // Table columns / fields
    'employee'           => 'Employee',
    'role'               => 'Role',
    'team'               => 'Team',
    'no_team'            => 'No team',
    'branch'             => 'Branch',
    'contact'            => 'Contact',
    'hire_date'          => 'Hire date',
    'status'             => 'Status',
    'actions'            => 'Actions',
    'view'               => 'View details',
    'leader_tag'         => 'Leader',

    // Detail
    'back_to_list'       => 'Back to list',
    'leader_of'          => 'Leader of :team',
    'personal_info'      => 'Personal information',
    'date_of_birth'      => 'Date of birth',
    'email'              => 'Email',
    'phone'              => 'Phone',
    'work_info'          => 'Work information',
    'job_title'          => 'Job title',
    'seniority_label'    => 'Seniority',
    'seniority'          => ':years yr :months mo',
    'seniority_years'    => ':years yr',
    'seniority_months'   => ':months mo',
    'seniority_new'      => 'Less than 1 month',

    'team_section'       => 'Sales team',
    'no_team_note'       => 'This employee is not in any sales team.',
    'team_leader'        => 'Team leader',
    'established_date'   => 'Established',
    'members'            => 'Members',
    'teammates'          => 'Teammates',
    'no_teammates'       => 'No other members in this team yet.',

    'account'            => 'Login account',
    'username'           => 'Username',
    'account_status'     => 'Status',
    'account_active'     => 'Active',
    'account_inactive'   => 'Locked',
    'account_created'    => 'Created',
    'no_account'         => 'This employee has no account yet.',

    'performance'        => 'Sales performance',
    'performance_note'   => 'Based on all data handled by this employee',
    'opportunities'      => 'Opportunities',
    'in_progress'        => ':count in progress',
    'won'                => 'Won opportunities',
    'win_rate'           => 'Win rate :rate',
    'quotations'         => 'Quotations created',
    'confirmed'          => ':count confirmed',
    'appointments'       => 'Appointments',
];
