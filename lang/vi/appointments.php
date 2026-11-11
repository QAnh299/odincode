<?php

declare(strict_types=1);

// Trang Danh sách lịch hẹn của Salesperson (livewire/appointments/index) + nhắc lịch hẹn ở chuông thông báo
return [
    'title'              => 'Lịch hẹn',
    'subtitle'           => 'Các lịch hẹn test / tư vấn bạn đang phụ trách',

    'stat' => [
        'total'     => 'Tổng lịch hẹn',
        'Scheduled' => 'Đã lên lịch',
        'Success'   => 'Khách đã đến',
        'Failed'    => 'Khách không đến',
    ],

    'filters'            => 'Lọc lịch hẹn',
    'search_placeholder' => 'Tìm theo tên khách, SĐT, mã cơ hội',
    'all_statuses'       => 'Tất cả trạng thái',
    'all_periods'        => 'Tất cả thời gian',
    'period' => [
        'today'    => 'Hôm nay',
        'tomorrow' => 'Ngày mai',
        'week'     => '7 ngày tới',
        'month'    => 'Tháng này',
        'past'     => 'Đã qua giờ hẹn',
    ],
    'reset'              => 'Xoá bộ lọc',
    'loading'            => 'Đang tải…',
    'empty'              => 'Không có lịch hẹn nào',
    'empty_sub'          => 'Thử đổi trạng thái, khoảng thời gian hoặc từ khoá tìm kiếm.',
    'showing'            => 'Hiển thị :shown trên :total lịch hẹn',

    'customer'           => 'Khách hàng',
    'scheduled_time'     => 'Thời gian hẹn',
    'type'               => 'Hình thức',
    'location'           => 'Địa điểm',
    'actions'            => 'Thao tác',
    'opportunity'        => 'Cơ hội',
    'prev'               => 'Trước',
    'next'               => 'Sau',
    'opportunity_ref'    => 'Cơ hội :code',
    'open_detail'        => 'Xem lịch hẹn trong chi tiết cơ hội',
    'today'              => 'Hôm nay',
    'tomorrow'           => 'Ngày mai',

    // Dùng chung (báo cáo, lọc trạng thái)
    'status' => [
        'Scheduled' => 'Đã lên lịch',
        'Overdue'   => 'Chờ cập nhật kết quả',
        'Success'   => 'Khách đã đến',
        'Failed'    => 'Khách không đến',
        'Cancelled' => 'Đã hủy',
    ],
    // Nhãn ngắn trong bảng
    'badge' => [
        'Scheduled' => 'Đã lên lịch',
        'Overdue'   => 'Chờ cập nhật',
        'Success'   => 'Đã đến',
        'Failed'    => 'Không đến',
        'Cancelled' => 'Đã hủy',
    ],

    // Popup thao tác
    'action' => [
        'reschedule' => 'Đổi lịch',
        'result'     => 'Cập nhật kết quả',
        'cancel'     => 'Hủy lịch',
        'wait'       => 'Cập nhật kết quả được từ giờ hẹn',
    ],
    'dialog' => [
        'result_title'     => 'Cập nhật kết quả lịch hẹn',
        'reschedule_title' => 'Đổi lịch hẹn',
        'cancel_title'     => 'Hủy lịch hẹn',
        'outcome'          => 'Kết quả',
        'attended'         => 'Khách đã đến',
        'attended_hint'    => 'Ghi kết quả buổi hẹn',
        'absent'           => 'Khách không đến',
        'absent_hint'      => 'Có thể liên hệ xếp lại lịch sau',
        'current_time'     => 'Thời gian hiện tại',
        'new_time'         => 'Thời gian mới',
        'cancel_warning'   => 'Nếu khách không còn buổi hẹn nào đang chờ và chưa đến buổi nào, cơ hội sẽ chuyển sang Lưu trữ (Lost).',
        'save_result'      => 'Lưu kết quả',
        'save_reschedule'  => 'Lưu lịch mới',
        'save_cancel'      => 'Xác nhận hủy',
        'close'            => 'Đóng',
        'back'             => 'Quay lại',
    ],
    'rescheduled'        => 'Đã đổi lịch hẹn sang :time.',
    'cannot_change'      => 'Không đổi / hủy được: lịch hẹn đã tới giờ hẹn, đã có kết quả hoặc cơ hội đã tới stage Chốt.',

    // Chuông thông báo
    'reminder_title'     => 'Lịch hẹn :types với :name',
    'overdue_title'      => 'Cập nhật kết quả lịch hẹn :types với :name',
    'overdue_time'       => 'Đã qua giờ hẹn :time',
    'reminder_header'    => 'Lịch hẹn hôm nay & ngày mai - nhớ nhắc khách',
    'view_all'           => 'Xem tất cả lịch hẹn',
];
