<?php

declare(strict_types=1);

// Lịch việc cần làm trên topbar (livewire/task-calendar.blade.php)
return [
    'title'         => 'Lịch việc cần làm',
    'month'         => 'Tháng :month, :year',
    'month_summary' => ':total hoạt động · còn :todo việc',
    'day_summary'   => ':total hoạt động · :done đã xong',
    'previous'      => 'Tháng trước',
    'next'          => 'Tháng sau',
    'close'         => 'Đóng danh sách',
    'open'          => 'Mở',
    'overdue'       => 'Quá hạn',
    'empty'         => 'Không có hoạt động nào trong ngày này.',

    'weekday_short' => [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'],
    'weekday'       => [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'],

    'legend' => [
        'todo'    => 'Việc còn lại',
        'overdue' => 'Quá hạn chưa xong',
        'done'    => 'Đã xong hết',
    ],

    'kind' => [
        'initial'   => 'Liên hệ ban đầu',
        'recontact' => 'Liên hệ lại',
        'quotation' => 'Báo giá hết hạn',
        'care'      => 'Chăm sóc',
    ],
];
