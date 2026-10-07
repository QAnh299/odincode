<?php

declare(strict_types=1);

// Trang khóa học: danh sách (livewire/courses/index) và chi tiết (livewire/courses/show)
return [
    'title'              => 'Danh sách khóa học',
    'subtitle'           => 'Tra cứu thông tin khóa học, học phí và trạng thái cung cấp',
    'detail_title'       => 'Khóa học :code',

    // Tra cứu
    'lookup'             => 'Tra cứu khóa học',
    'search'             => 'Tìm kiếm',
    'search_placeholder' => 'Tìm theo mã hoặc tên khóa học (VD: CRS001, IELTS)…',
    'all'                => 'Tất cả',
    'reset'              => 'Xoá bộ lọc',
    'loading'            => 'Đang tải…',
    'result_count'       => 'Tìm thấy :count khóa học',
    'empty'              => 'Không tìm thấy khóa học nào',
    'empty_sub'          => 'Thử đổi từ khoá hoặc bỏ bớt bộ lọc.',

    'state' => [
        'all'      => 'Tất cả khóa học',
        'active'   => 'Đang cung cấp',
        'inactive' => 'Ngừng cung cấp',
    ],

    // Cột bảng / trường thông tin
    'code'               => 'Mã khóa học',
    'name'               => 'Tên khóa học',
    'description'        => 'Mô tả',
    'price'              => 'Học phí',
    'vat'                => 'VAT',
    'duration'           => 'Thời lượng',
    'status'             => 'Trạng thái',
    'created_at'         => 'Ngày tạo',
    'actions'            => 'Thao tác',
    'view'               => 'Xem chi tiết',

    // Chi tiết
    'back'               => 'Quay lại',
    'info'               => 'Thông tin khóa học',
    'read_only'          => 'Chỉ xem, không thể chỉnh sửa thông tin khóa học',
    'no_description'     => 'Chưa có mô tả',

    // Tổng quan kinh doanh
    'overview'           => 'Tổng quan kinh doanh',
    'overview_sub'       => 'Tổng hợp từ toàn bộ đơn hàng (trừ đơn đã huỷ) và hoá đơn đã thanh toán có khóa học này',
    'scope_note'         => 'Chỉ tính đơn hàng trong phạm vi bạn được xem',
    'registrations'      => 'Số lượt đăng ký',
    'revenue'            => 'Doanh thu',
    'paid'               => 'Đã thu',
    'remaining'          => 'Còn phải thu',
    'students'           => 'Số học viên',
    'collected_rate'     => 'Tỷ lệ đã thu',

    // Lịch sử kinh doanh
    'history'            => 'Lịch sử kinh doanh',
    'history_sub'        => 'Số liệu theo tháng tạo đơn hàng; đã thu / còn phải thu tính đến hiện tại',
    'from_month'         => 'Từ tháng',
    'to_month'           => 'Đến tháng',
    'default_period'     => '12 tháng gần nhất',
    'period_note'        => 'Đang xem :from – :to (:count tháng)',
    'month'              => 'Tháng',
    'month_label'        => 'Tháng :month',
    'total'              => 'Tổng cộng',
    'no_sales'           => 'Chưa có đơn hàng trong khoảng thời gian này',

    // Biểu đồ
    'chart_revenue'       => 'Doanh thu theo tháng',
    'chart_registrations' => 'Số lượt đăng ký theo tháng',
    'registration_count'  => ':count lượt đăng ký',
    'unit_thousand'       => 'k',
    'unit_million'        => 'tr',
    'unit_billion'        => 'tỷ',
];
