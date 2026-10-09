<?php

declare(strict_types=1);

// Lead list page (livewire/leads/index)
return [
    'title'                  => 'Leads',
    'subtitle'               => 'Track newly added leads and their distribution to the sales teams',
    'subtitle_branch'        => 'Leads of :branch',

    // Action buttons (not functional yet)
    'add'                    => 'Add lead',
    'import'                 => 'Import Excel',
    'distribute_team'        => 'Distribute leads to sales teams',
    'distribute_salesperson' => 'Distribute leads to salespeople',

    // Statistics
    'stat' => [
        'all'       => 'Total leads',
        'New'       => 'Not distributed',
        'Converted' => 'Converted',
        'rate'      => 'Conversion rate',
    ],

    // Filters
    'filters'                => 'Lead filters',
    'search'                 => 'Search',
    'search_placeholder'     => 'Search by code, name, phone or email…',
    'all'                    => 'All',
    'all_branches'           => 'All branches',
    'from'                   => 'From',
    'to'                     => 'To',
    'reset'                  => 'Clear filters',
    'loading'                => 'Loading…',
    'result_count'           => ':count lead(s) found',
    'empty'                  => 'No leads found',
    'empty_sub'              => 'Try another keyword or remove some filters.',

    // Table columns
    'code'                   => 'Lead code',
    'customer'               => 'Customer',
    'phone'                  => 'Phone',
    'source'                 => 'Source',
    'contact_method'         => 'Contact channel',
    'branch'                 => 'Branch',
    'created_at'             => 'Created',
    'owner'                  => 'Owner',
    'unassigned'             => 'Not distributed',
    'status'                 => 'Status',

    'status_name' => [
        'New'       => 'New',
        'Converted' => 'Converted',
    ],

    'method' => [
        'Zalo'     => 'Zalo',
        'Phone'    => 'Phone',
        'Facebook' => 'Facebook',
    ],
];
