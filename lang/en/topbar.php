<?php

declare(strict_types=1);

// Top navigation bar (components/topbar.blade.php)
return [
    'notifications'    => 'Notifications',
    'no_notifications' => 'No notifications yet',
    'mark_read'        => 'Mark as read',
    'mark_all_read'    => 'Mark all as read',
    'unread_count'     => ':count unread',
    'mark_tab_read'    => 'Mark as read',
    'no_notifications_tab' => 'No notifications in this section',
    // Notification group tabs
    'notify_tab' => [
        'all'         => 'All',
        'care'        => 'Contact',
        'appointment' => 'Appointments',
        'quotation'   => 'Quotations',
        'invoice'     => 'Invoices',
        'cash'        => 'Pending cash',
        'transfer'    => 'Transfers',
        'paid'        => 'Paid',
        'lead'        => 'Lead assignment',
    ],
    'notify_footer' => [
        'care'      => 'View opportunities to follow up',
        'quotation' => 'View all quotations',
        'invoice'   => 'View all invoices',
    ],
    'profile'          => 'My profile',
    'change_password'  => 'Change password',
    'settings'         => 'Settings',
    'language'         => 'Language',
];
