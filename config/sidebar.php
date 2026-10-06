<?php

/*
|--------------------------------------------------------------------------
| Menu dọc bên trái theo vai trò
|--------------------------------------------------------------------------
| items: định nghĩa từng mục menu (cả mục lớn lẫn menu con).
|   - route: hậu tố route name, ghép với prefix vai trò.
|            VD: vai trò director + route 'leads' → route name 'director.leads'.
|            Route chưa tồn tại thì link tạm là '#'.
|   - icon:  tên icon trong resources/views/components/sidebar.blade.php
|            (menu con không cần icon).
|   Nhãn hiển thị lấy từ lang/{locale}/menu.php theo key của item.
|
| roles: các mục menu (đúng thứ tự hiển thị) của từng vai trò.
|   - 'leads'                          → mục thường, bấm vào mở trang.
|   - 'leads' => ['lead_list', ...]    → mục lớn có menu con; mỗi vai trò
|                                        có thể có menu con khác nhau.
*/

return [
    'items' => [
        'dashboard'     => ['route' => 'home',         'icon' => 'dashboard'],
        'leads'         => ['route' => 'leads',        'icon' => 'lead'],
        'employees'     => ['route' => 'employees',    'icon' => 'employee'],
        'quotations'    => ['route' => 'quotations',   'icon' => 'quotation'],
        'orders'        => ['route' => 'orders',       'icon' => 'order'],
        'invoices'      => ['route' => 'invoices',     'icon' => 'invoice'],
        'students'      => ['route' => 'students',     'icon' => 'student'],
        'courses'       => ['route' => 'courses',      'icon' => 'course'],
        'vouchers'      => ['route' => 'vouchers',     'icon' => 'voucher'],

        // Menu con
        'employee_list' => ['route' => 'employees'],
        'sales_teams'   => ['route' => 'sales-teams'],
        'lead_list'     => ['route' => 'leads'],
        'appointments'  => ['route' => 'appointments'],
    ],

    'roles' => [
        'director' => [
            'dashboard',
            'leads',
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
            'employees' => ['employee_list', 'sales_teams'],
            'quotations',
            'orders',
            'invoices',
            'students',
            'courses',
            'vouchers',
            'dashboard',
        ],

        'sale_leader' => [
            'leads',
            'employees',
            'quotations',
            'orders',
            'invoices',
            'students',
            'courses',
            'vouchers',
            'dashboard',
        ],

        'salesperson' => [
            'leads' => ['lead_list', 'appointments'],
            'quotations',
            'orders',
            'invoices',
            'students',
            'courses',
            'vouchers',
            'dashboard',
        ],

        'accountant' => [
            'quotations',
            'orders',
            'invoices',
            'students',
            'dashboard',
        ],
    ],
];
