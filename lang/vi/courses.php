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
    'price'              => 'Học phí',
    'vat'                => 'VAT',
    'duration'           => 'Thời lượng',
    'status'             => 'Trạng thái',
    'actions'            => 'Thao tác',
    'view'               => 'Xem chi tiết',

    // Chi tiết: nội dung khóa học
    'back_to_list'       => 'Quay lại danh sách',
    'content'            => 'Nội dung khóa học',
    'overview_heading'   => 'Tổng quan',
    'no_description'     => 'Chưa có mô tả',
    'created_on'         => 'Khóa học được tạo ngày :date',

    // Chi tiết: thống kê
    'stats'              => 'Thống kê',
    'quotations_created' => 'Báo giá đã tạo',
    'seats_confirmed'    => 'Suất đã chốt',
    'close_rate'         => 'Tỷ lệ chốt',
    'close_rate_hint'    => 'Đã xác nhận ÷ (Đã xác nhận + Từ chối)',
    'revenue_confirmed'  => 'Doanh thu đã chốt',
    'before_voucher'     => 'Chưa trừ voucher',
    'scope_own'          => 'Chỉ tính báo giá do bạn lập',
    'scope_team'         => 'Chỉ tính báo giá của đội :team',
    'scope_employee'     => 'Chỉ tính báo giá của :name (:team)',

    // Chi tiết: bộ lọc phạm vi (Sale Admin, Sale Leader)
    'scope_filter'       => 'Phạm vi xem',
    'team'               => 'Đội',
    'all_teams'          => 'Tất cả đội',
    'no_team'            => 'Chưa thuộc đội nào',
    'employee'           => 'Nhân viên',
    'all_employees'      => 'Tất cả',
    'choose_team_first'  => 'Chọn đội trước',
    'resigned'           => 'đã nghỉ',
    'clear_scope'        => 'Bỏ lọc',

    // Chi tiết: báo giá theo trạng thái
    'by_status'          => 'Báo giá theo trạng thái',
    'filter_by_status'   => 'Lọc danh sách báo giá theo trạng thái',

    'quotation_status' => [
        'Draft'     => 'Đang soạn',
        'Confirmed' => 'Đã xác nhận',
        'Rejected'  => 'Từ chối',
    ],

    // Chi tiết: danh sách báo giá
    'quotations_title'     => 'Báo giá có khóa học này',
    'quotation_id'         => 'Mã báo giá',
    'quotation_created_at' => 'Ngày tạo',
    'quantity'             => 'Số lượng',
    'no_quotations'        => 'Chưa có báo giá nào',
    'no_quotations_status' => 'Không có báo giá nào ở trạng thái này',
    'clear_filter'         => 'Xem tất cả báo giá',
];
