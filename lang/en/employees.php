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
    'profile_title'      => 'Employee information',
    'seniority_value'    => ':value of service',
    'team_since'         => 'Since :date',
    'team_members'       => 'Team members',
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


    // Sales performance (Sale Leader / Salesperson detail) – same rules as the Sales performance report
    'perf' => [
        'title'          => 'Sales performance',
        'title_team'     => 'Sales performance of team :team',
        'no_team'        => 'This Sale Leader does not lead any sales team yet, so there is no data.',
        'period'         => 'Period',
        'month'          => 'Month :month',
        'all_time'       => 'All time',
        'open_report'    => 'Open detailed report',
        'kpi' => [
            'revenue'  => 'Revenue',
            'won'      => 'Won leads',
            'students' => 'New students',
            'assigned' => 'Assigned leads',
        ],
        'paid'           => ':amount collected',
        'win_rate'       => 'Win rate :rate · :lost lost',
        'processed'      => ':rate processed',
        'target'         => ':rate% of target (:target)',
        'target_reached' => ':rate% of target',
        'delta'          => ':value% vs previous month',
        'delta_new'      => 'None last month',
        'delta_new_short' => 'New',
        'delta_note'     => 'The % badge next to each figure compares with the previous month.',
    ],

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

        // Create team / Edit team / Assign leader popups (Sale Admin)
        'form' => [
            'create'           => 'Create team',
            'create_sub'       => 'The team code is generated automatically (e.g. TEAM03). You can assign a leader now or later.',
            'edit'             => 'Edit team',
            'edit_sub'         => 'Update the team name, established date, leader and the Salespeople in the team.',
            'team_name'        => 'Team name',
            'placeholder_name' => 'e.g. ODIN Sales 3',
            'no_leader_yet'    => '— No leader yet —',
            'current'          => 'current leader',
            'in_team'          => 'in :team',
            'leader_hint'      => 'Changing the leader moves the new leader into the team and removes the previous one. Once members are selected, only leaders in their branch can be chosen.',
            'no_candidates'    => 'No Sale Leader is available: each Sale Leader leads only one team.',
            'required_hint'    => 'Fields marked * are required.',
            'name_taken'       => 'This team name already exists.',
            'date_future'      => 'The established date cannot be after today.',
            'leader_invalid'   => 'The leader must be a working Sale Leader in the team\'s branch who does not lead another team.',
            'members'          => 'Members (Salespeople)',
            'selected'         => '· :count selected',
            'member_search'    => 'Filter by name or employee code…',
            'members_hint'     => 'Tick to add to the team, untick to remove. Each employee belongs to one team and each team to one branch: people in another team or in a different branch from the leader cannot be selected.',
            'no_salespeople'   => 'There are no working Salespeople yet.',
            'member_invalid'   => 'Some employees are invalid, already belong to another team or are in a different branch.',
            'member_mixed_branch' => 'All members of a team must be in the same branch.',
            'team_branch'      => 'Team branch',
            'branch_undecided' => 'Not decided yet (set by the selected leader or members)',
            'create_branch_hint' => 'Each team belongs to one branch: the team branch is the leader\'s branch and members must be in it.',
            'no_candidates_branch' => 'No Sale Leader is available in branch :branch (each Sale Leader leads only one team).',
            'save'             => 'Save',
            'cancel'           => 'Cancel',
            'close'            => 'Close',
            'saved_create'     => 'Team :name (:id) created.',
            'saved_edit'       => 'Team :name updated.',
        ],

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
