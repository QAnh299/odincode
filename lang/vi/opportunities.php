<?php

declare(strict_types=1);

// Trang Quản lý Opportunity dạng Kanban (livewire/opportunities/kanban)
return [
    'title'              => 'Quản lý Opportunity',
    'subtitle'           => 'Các cơ hội bạn đang phụ trách, chia theo giai đoạn chăm sóc',

    'status'             => 'Trạng thái',
    'result_count'       => ':count Opportunity',

    'empty_stage'        => 'Chưa có Opportunity',
    'no_value'           => 'Chưa có giá trị dự kiến',
    'conversion_date'    => 'Ngày chuyển đổi',

    // Chi tiết
    'detail_title'       => 'Opportunity :code',
    'back'               => 'Quay lại Kanban',
    'create_quotation'   => 'Tạo báo giá',
    'stage'              => 'Giai đoạn',
    'customer'           => 'Thông tin khách hàng',
    'email'              => 'Email',
    'lead'               => 'Lead gốc',
    'info'               => 'Thông tin cơ hội',
    'expected_value'     => 'Giá trị dự kiến',
    'student'            => 'Học viên',

    'care_history'       => 'Lịch sử chăm sóc',
    'no_care'            => 'Chưa có hoạt động chăm sóc nào.',
    'care_result' => [
        'Success' => 'Thành công',
        'Failed'  => 'Không thành công',
    ],

    'appointments'       => 'Lịch hẹn',
    'no_appointments'    => 'Chưa có lịch hẹn nào.',
    'appointment_time'   => 'Thời gian',
    'appointment_type'   => 'Hình thức',
    'location'           => 'Địa điểm',
    'notes'              => 'Ghi chú',
    'appointment_type_name' => [
        'Phone'        => 'Gọi điện',
        'Consultation' => 'Tư vấn trực tiếp',
        'Online'       => 'Trực tuyến',
    ],
    'appointment_status' => [
        'Scheduled' => 'Đã lên lịch',
        'Success'   => 'Thành công',
        'Failed'    => 'Không thành công',
    ],

    'quotations'           => 'Báo giá',
    'no_quotations'        => 'Chưa có báo giá. Chỉ tạo được báo giá khi cơ hội ở giai đoạn Chốt.',
    'no_quotations_closed' => 'Chưa có báo giá. Bấm "Tạo báo giá" để lập báo giá cho khách hàng.',
    'quotation_id'         => 'Mã báo giá',
    'created_at'           => 'Ngày tạo',
    'expiry_date'          => 'Hiệu lực đến',
    'voucher'              => 'Voucher',
    'quotation_total'      => 'Tổng tiền',
    'total_hint'           => 'Tổng tiền là tổng các dòng khóa học (đã gồm VAT), chưa trừ voucher.',

    'student_status' => [
        'Studying'  => 'Đang học',
        'Dropped'   => 'Đã nghỉ',
        'Completed' => 'Đã hoàn thành',
    ],
];
