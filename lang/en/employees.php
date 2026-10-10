<?php

declare(strict_types=1);

// Employee pages: list (livewire/employees/index), detail (livewire/employees/show)
// and sales teams (livewire/sales-teams/index)
return [
    'title'              => 'Employees',
    'subtitle'           => 'Look up employee information, roles, sales teams and branches',
    'subtitle_sale_admin' => 'Sale Leaders and Salespeople under your management',
    'subtitle_team'      => 'Salespeople in your team :team',
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
    'view_team'          => 'View team',
    'no_team_note'       => 'This employee is not in any sales team.',
    'team_leader'        => 'Team leader',
    'established_date'   => 'Established',
    'members'            => 'Members',
    'teammates'          => 'Teammates',
    'no_teammates'       => 'No other members in this team yet.',


    'performance'        => 'Sales performance',
    'performance_note'   => 'Based on all data handled by this employee',
    'opportunities'      => 'Opportunities',
    'in_progress'        => ':count in progress',
    'won'                => 'Won opportunities',
    'win_rate'           => 'Win rate :rate',
    'quotations'         => 'Quotations created',
    'confirmed'          => ':count confirmed',
    'appointments'       => 'Appointments',

    // Sales teams
    'teams' => [
        'title'              => 'Sales teams',
        'subtitle'           => 'Sales teams, their leaders and members',
        'lookup'             => 'Sales team lookup',
        'search_placeholder' => 'Search by team code, name, leader or member (e.g. TEAM01, Huy)…',
        'result_count'       => ':count team(s) found',
        'empty'              => 'No teams found',
        'team'               => 'Team',
        'no_leader'          => 'No leader yet',
        'member_count'       => ':count member(s)',
        'view_members'       => 'View employees',

        'summary' => [
            'teams'   => 'Sales teams',
            'in_team' => 'Sales staff in a team',
            'without' => 'Sales staff without a team',
        ],

        'sort' => [
            'name'    => 'Team name (A → Z)',
            'newest'  => 'Newest first',
            'oldest'  => 'Oldest first',
            'members' => 'Most members',
        ],
    ],
];
