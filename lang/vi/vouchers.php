<?php

declare(strict_types=1);

// Trang voucher: danh sách (livewire/vouchers/index) và chi tiết (livewire/vouchers/show)
return [
    'title'              => 'Quản lý voucher',
    'subtitle'           => 'Tra cứu mã giảm giá, thời gian hiệu lực và tình hình áp dụng',
    'detail_title'       => 'Voucher :code',

    // Tra cứu
    'lookup'             => 'Tra cứu voucher',
    'search'             => 'Tìm kiếm',
    'search_placeholder' => 'Tìm theo mã voucher hoặc giá trị giảm (VD: VCH001, 10, 500000)…',
    'all'                => 'Tất cả',
    'discount_type'      => 'Loại giảm giá',
    'valid_from'         => 'Hiệu lực từ ngày',
    'valid_to'           => 'Đến ngày',
    'sort_by'            => 'Sắp xếp',
    'reset'              => 'Xoá bộ lọc',
    'loading'            => 'Đang tải…',
    'result_count'       => 'Tìm thấy :count voucher',
    'empty'              => 'Không tìm thấy voucher nào',
    'empty_sub'          => 'Thử đổi từ khoá hoặc bỏ bớt bộ lọc.',

    'sort' => [
        'newest' => 'Mới bắt đầu gần đây',
        'oldest' => 'Bắt đầu sớm nhất',
        'ending' => 'Sắp hết hạn trước',
        'code'   => 'Mã voucher (A → Z)',
    ],

    'type' => [
        'percentage' => 'Giảm theo %',
        'fixed'      => 'Giảm số tiền',
    ],

    'state' => [
        'all'      => 'Tất cả voucher',
        'active'   => 'Hoạt động',
        'inactive' => 'Không hoạt động',
    ],

    // Cột bảng
    'code'               => 'Mã voucher',
    'value'              => 'Giá trị giảm',
    'validity'           => 'Thời gian hiệu lực',
    'status'             => 'Trạng thái',
    'used'               => 'Báo giá đã áp dụng',
    'view'               => 'Xem chi tiết',
    'progress'           => 'Đã qua :percent% thời gian hiệu lực',

    // Chi tiết
    'back'               => 'Quay lại',
    'copy'               => 'Sao chép mã',
    'copied'             => 'Đã sao chép',
    'caption_percentage' => 'trên tổng giá trị báo giá',
    'caption_fixed'      => 'trừ trực tiếp vào báo giá',
    'start_date'         => 'Ngày bắt đầu',
    'end_date'           => 'Ngày kết thúc',
    'today'              => 'Hôm nay',
    'total_days'         => 'Tổng :days ngày',
    'days_left'          => 'Còn :days ngày hiệu lực',
    'ends_today'         => 'Hết hạn vào hôm nay',
    'starts_in'          => 'Bắt đầu sau :days ngày',
    'ended_ago'          => 'Đã hết hạn :days ngày',
    'subtotal_sum'       => 'Tổng giá trị trước giảm',
    'discount_sum'       => 'Tổng tiền đã giảm',

    'quotations'         => 'Báo giá đã áp dụng voucher',
    'quotations_sub'     => 'Toàn bộ báo giá trong hệ thống có dùng voucher này',
    'scope_note'         => 'Chỉ hiển thị báo giá trong phạm vi bạn được xem',
    'no_quotations'      => 'Chưa có báo giá nào áp dụng voucher này',
    'quotation_id'       => 'Mã báo giá',
    'customer'           => 'Khách hàng',
    'employee'           => 'Nhân viên',
    'created_at'         => 'Ngày tạo',
    'subtotal'           => 'Trước giảm',
    'discount'           => 'Tiền giảm',

    'quotation_status' => [
        'Draft'     => 'Nháp',
        'Sent'      => 'Đã gửi',
        'Confirmed' => 'Đã xác nhận',
        'Rejected'  => 'Từ chối',
        'Expired'   => 'Hết hạn',
        'Cancelled' => 'Đã huỷ',
    ],
];
