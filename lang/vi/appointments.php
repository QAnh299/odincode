<?php

declare(strict_types=1);

// Trang Lịch hẹn của Salesperson (livewire/appointments/index) + nhắc lịch hẹn ở chuông thông báo
return [
    'title'              => 'Lịch hẹn',
    'subtitle'           => 'Các lịch hẹn test / tư vấn bạn đang phụ trách',

    'stat' => [
        'today'    => 'Hôm nay',
        'tomorrow' => 'Ngày mai',
        'week'     => '7 ngày tới',
        'overdue'  => 'Chờ cập nhật kết quả',
    ],

    'filters'            => 'Lọc lịch hẹn',
    'view_label'         => 'Xem',
    'view' => [
        'upcoming' => 'Sắp tới',
        'today'    => 'Hôm nay',
        'tomorrow' => 'Ngày mai',
        'week'     => '7 ngày tới',
        'overdue'  => 'Chờ cập nhật kết quả',
        'done'     => 'Đã có kết quả',
        'all'      => 'Tất cả',
    ],
    'search'             => 'Tìm kiếm',
    'search_placeholder' => 'Tìm theo tên, SĐT khách hàng, mã lịch hẹn hoặc mã cơ hội…',
    'all_types'          => 'Tất cả loại',
    'reset'              => 'Xoá bộ lọc',
    'loading'            => 'Đang tải…',
    'result_count'       => ':count lịch hẹn',
    'empty'              => 'Không có lịch hẹn nào',
    'empty_sub'          => 'Thử chọn chế độ xem khác hoặc bỏ bớt bộ lọc.',

    'time'               => 'Thời gian',
    'customer'           => 'Khách hàng',
    'opportunity'        => 'Cơ hội',
    'actions'            => 'Thao tác',
    'view_detail'        => 'Xem chi tiết',
    'today'              => 'Hôm nay',
    'tomorrow'           => 'Ngày mai',

    'status' => [
        'Scheduled' => 'Đã lên lịch',
        'Overdue'   => 'Chờ cập nhật',
        'Success'   => 'Khách đã đến',
        'Failed'    => 'Khách không đến',
    ],

    // Chuông thông báo
    'reminder_title'     => 'Lịch hẹn :types với :name',
    'reminder_header'    => 'Lịch hẹn hôm nay & ngày mai - nhớ nhắc khách',
    'view_all'           => 'Xem tất cả lịch hẹn',
];
