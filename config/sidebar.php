<?php

return [
    'items' => [
        'dashboard'     => ['route' => 'home',         'icon' => 'dashboard'],
        'leads'         => ['route' => 'leads',        'icon' => 'lead'],
        'opportunities' => ['route' => 'opportunities', 'icon' => 'opportunity'],
        'employees'     => ['route' => 'employees',    'icon' => 'employee'],
        'quotations'    => ['route' => 'quotations',   'icon' => 'quotation'],
        'orders'        => ['route' => 'orders',       'icon' => 'order'],
        'invoices'      => ['route' => 'invoices',     'icon' => 'invoice'],
        'students'      => ['route' => 'students',     'icon' => 'student'],
        'courses'       => ['route' => 'courses',      'icon' => 'course'],
        'vouchers'      => ['route' => 'vouchers',     'icon' => 'voucher'],

        'employee_list' => ['route' => 'employees'],
        'sales_teams'   => ['route' => 'sales-teams'],
        'lead_list'     => ['route' => 'leads'],
        'opportunity_list' => ['route' => 'opportunities'],
        'appointments'  => ['route' => 'appointments'],

        'report_revenue'           => ['route' => 'reports.revenue'],
        'report_lead_conversion'   => ['route' => 'reports.lead-conversion'],
        'report_sales_performance' => ['route' => 'reports.sales-performance'],
        'report_lead_sources'      => ['route' => 'reports.lead-sources'],
    ],

    'roles' => [
        'director' => [
            'dashboard' => [
                'report_revenue',
                'report_lead_conversion',
                'report_sales_performance',
                'report_lead_sources',
            ],
            'leads',
            'opportunities',
            'employees' => ['employee_list', 'sales_teams'],
            'quotations',
            'orders',
            'invoices',
            'students',
            'courses',
            'vouchers',
        ],

        'sale_admin' => [
            'leads',
            'opportunities',
            'employees' => ['employee_list', 'sales_teams'],
            'quotations',
            'orders',
            'invoices',
            'students',
            'courses',
            'vouchers',
            'dashboard' => [
                'report_revenue',
                'report_lead_conversion',
                'report_sales_performance',
                'report_lead_sources',
            ],
        ],

        'sale_leader' => [
            'leads',
            'opportunities',
            'employees',
            'quotations',
            'orders',
            'invoices',
            'students',
            'courses',
            'vouchers',
            'dashboard' => [
                'report_revenue',
                'report_lead_conversion',
                'report_sales_performance',
            ],
        ],

        'salesperson' => [
            'opportunities' => ['opportunity_list', 'appointments'],
            'quotations',
            'orders',
            'invoices',
            'students',
            'courses',
            'vouchers',
            'dashboard' => [
                'report_revenue',
                'report_lead_conversion',
            ],
        ],

        'accountant' => [
            'quotations',
            'orders',
            'invoices',
            'students',
            'dashboard' => [
                'report_revenue',
            ],
        ],
    ],
];
