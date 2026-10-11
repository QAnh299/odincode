<?php

declare(strict_types=1);

// Trang học viên: danh sách (livewire/students/index) và chi tiết (livewire/students/show)
return [
    'title'              => 'Danh sách học viên',
    'subtitle'           => 'Khách hàng đã thanh toán toàn bộ học phí của khóa học đăng ký',
    'detail_title'       => 'Học viên :code',

    'stat' => [
        'all'       => 'Tổng học viên',
        'Studying'  => 'Đang học',
        'Completed' => 'Đã hoàn thành',
        'Dropped'   => 'Đã nghỉ',
    ],
    'status_name' => [
        'Studying'  => 'Đang học',
        'Completed' => 'Đã hoàn thành',
        'Dropped'   => 'Đã nghỉ',
    ],

    // Tra cứu
    'lookup'             => 'Tra cứu học viên',
    'search'             => 'Tìm kiếm',
    'search_placeholder' => 'Tìm theo mã, họ tên, SĐT hoặc email…',
    'all'                => 'Tất cả',
    'all_courses'        => 'Tất cả khóa học',
    'team'               => 'Đội',
    'all_teams'          => 'Tất cả đội',
    'salesperson'        => 'Salesperson',
    'all_salespeople'    => 'Tất cả salesperson',
    'choose_team_first'  => 'Chọn đội trước',
    'resigned'           => 'đã nghỉ',
    'from'               => 'Từ ngày',
    'to'                 => 'Đến ngày',
    'reset'              => 'Xoá bộ lọc',
    'loading'            => 'Đang tải…',
    'result_count'       => 'Tìm thấy :count học viên',
    'empty'              => 'Không tìm thấy học viên nào',
    'empty_sub'          => 'Thử đổi từ khoá hoặc bỏ bớt bộ lọc.',

    // Cột / trường
    'code'               => 'Mã học viên',
    'student'            => 'Học viên',
    'full_name'          => 'Họ và tên',
    'phone'              => 'Số điện thoại',
    'email'              => 'Email',
    'date_of_birth'      => 'Ngày sinh',
    'course'             => 'Khóa học',
    'conversion_date'    => 'Ngày trở thành học viên',
    'owner'              => 'Người phụ trách',
    'status'             => 'Trạng thái',
    'actions'            => 'Thao tác',
    'view'               => 'Xem chi tiết',

    // Chi tiết
    'back'               => 'Quay lại danh sách',
    'edit'               => 'Chỉnh sửa',
    'info'               => 'Thông tin học viên',
    'branch'             => 'Cơ sở',
    'origin'             => 'Nguồn gốc',
    'opportunity'        => 'Cơ hội',
    'lead'               => 'Lead',
    'other_enrollments'  => 'Các lần đăng ký khác',
    'courses_title'      => 'Khóa học đã đăng ký',
    'no_order'           => 'Không tìm thấy đơn hàng đã thanh toán đủ của học viên này.',
    'duration'           => 'Thời lượng',
    'unit_price'         => 'Học phí',
    'quantity'           => 'Số lượng',
    'line_total'         => 'Thành tiền',
    'payment_title'      => 'Thanh toán',
    'order'              => 'Đơn hàng',
    'payment_type'       => 'Hình thức',
    'total'              => 'Tổng tiền',
    'paid'               => 'Đã thanh toán',
    'quotation'          => 'Báo giá',
    'voucher'            => 'Voucher :code',
    'installment'        => 'Đợt',
    'installment_no'     => 'Đợt :no',
    'invoice'            => 'Hóa đơn',
    'amount'             => 'Số tiền',
    'method'             => 'Phương thức',
    'payment_date'       => 'Ngày thanh toán',

    // Chỉnh sửa
    'edit_title'         => 'Chỉnh sửa thông tin học viên',
    'save'               => 'Lưu thay đổi',
    'saved'              => 'Đã cập nhật thông tin học viên.',
    'phone_invalid'      => 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.',
    'dob_invalid'        => 'Ngày sinh phải trước hôm nay.',
];
