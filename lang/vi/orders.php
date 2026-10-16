<?php

declare(strict_types=1);

// Trang đơn hàng: danh sách (livewire/orders/index)
return [
    'title'              => 'Quản lý đơn hàng',
    'subtitle'           => 'Tra cứu đơn hàng tạo từ báo giá đã xác nhận: tổng tiền, tiến độ thanh toán và hóa đơn của từng đơn.',

    // Tìm kiếm
    'search'             => 'Tìm kiếm',
    'search_placeholder' => 'Tìm theo mã đơn, khách hàng, SĐT, email, báo giá, nhân viên, khóa học, voucher, mã hóa đơn, số tiền…',
    'search_hint'        => 'Nhiều từ khóa · "cụm chính xác" · -loại trừ',
    'search_help_title'  => 'Cách tìm kiếm',
    'search_help'        => [
        'Nhiều từ khóa: đơn phải khớp <strong>tất cả</strong>, VD <code>nguyen tra gop</code>',
        'Cụm chính xác: đặt trong ngoặc kép, VD <code>"Bảo Châu"</code>',
        'Loại trừ: thêm dấu trừ, VD <code>-ORD001</code>',
        'Không phân biệt hoa/thường và dấu: <code>toan bo</code> tìm ra "Toàn bộ"',
        'Số tiền: gõ đủ số, VD <code>5400000</code> hoặc <code>5.400.000</code> (so với tổng tiền, đã thanh toán, còn lại)',
        'Tìm trong: mã đơn, trạng thái, hình thức, ghi chú, mã báo giá / cơ hội / voucher / hóa đơn, tên/SĐT/email khách, nhân viên, khóa học, số tiền',
    ],

    // Thẻ thống kê
    'all'                => 'Tất cả',

    // Bộ lọc
    'filters'            => 'Bộ lọc',
    'close'              => 'Đóng',
    'active_filters'     => 'Đang lọc',
    'remove_filter'      => 'Bỏ bộ lọc',
    'all_employees'      => 'Tất cả nhân viên',
    'all_teams'          => 'Tất cả đội',
    'status_label'       => 'Trạng thái',
    'payment_type_label' => 'Hình thức thanh toán',
    'progress_label'     => 'Tiến độ thanh toán',
    'invoice_label'      => 'Hóa đơn',
    'amount_range'       => 'Tổng tiền (VNĐ)',
    'amount_from'        => 'Tổng tiền từ',
    'amount_to'          => 'Tổng tiền đến',
    'created_range'      => 'Ngày tạo đơn',
    'created_from'       => 'Ngày tạo từ',
    'created_to'         => 'Ngày tạo đến',
    'team'               => 'Đội bán hàng',
    'employee'           => 'Nhân viên',
    'invalid_amount'     => 'Số tiền phải là số không âm',
    'invalid_amount_range' => 'Số tiền kết thúc phải lớn hơn hoặc bằng số tiền bắt đầu',
    'invalid_range'      => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu',
    'sort_by'            => 'Sắp xếp',
    'reset'              => 'Xoá bộ lọc',
    'reset_all'          => 'Xoá tất cả bộ lọc',
    'reset_form'         => 'Đặt lại',
    'apply'              => 'Áp dụng',
    'pending'            => 'Bộ lọc đã thay đổi, bấm Áp dụng để cập nhật danh sách',
    'loading'            => 'Đang tải…',
    'result_count'       => 'Tìm thấy :count đơn hàng',
    'empty'              => 'Không tìm thấy đơn hàng phù hợp',
    'empty_sub'          => 'Thử đổi từ khoá hoặc bỏ bớt bộ lọc.',
    'empty_none'         => 'Chưa có đơn hàng nào',
    'empty_none_sub'     => 'Đơn hàng được tạo khi xác nhận báo giá.',

    'sort' => [
        'newest'         => 'Ngày tạo mới nhất',
        'oldest'         => 'Ngày tạo cũ nhất',
        'remaining_desc' => 'Còn lại cao → thấp',
        'total_desc'     => 'Tổng tiền cao → thấp',
        'total_asc'      => 'Tổng tiền thấp → cao',
        'code'           => 'Mã đơn hàng (A → Z)',
        'customer'       => 'Tên khách hàng (A → Z)',
    ],

    // Trạng thái đơn hàng (SalesOrder::STATUSES)
    'status' => [
        'InProgress' => 'Đang xử lý',
        'Completed'  => 'Hoàn tất',
    ],

    // Hình thức thanh toán (SalesOrder::PAYMENT_TYPES; none = NULL hoặc rỗng)
    'payment_type' => [
        'Full'        => 'Toàn bộ',
        'Installment' => 'Trả góp',
        'none'        => 'Chưa xác định',
    ],

    // Tiến độ thanh toán
    'progress' => [
        'unpaid'  => 'Chưa thanh toán',
        'partial' => 'Đang thanh toán',
        'paid'    => 'Đã thanh toán đủ',
    ],

    // Bộ lọc hóa đơn của đơn
    'invoice_state' => [
        'pending'       => 'Có hóa đơn đang chờ thanh toán',
        'overdue'       => 'Có hóa đơn quá hạn',
        'none'          => 'Chưa có hóa đơn nào',
        'needs_invoice' => 'Cần tạo hóa đơn tiếp',
    ],

    // Nhãn nhóm trên chip bộ lọc đang áp dụng
    'chip' => [
        'search'   => 'Từ khóa',
        'statuses' => 'Trạng thái',
        'payment'  => 'Hình thức',
        'progress' => 'Tiến độ',
        'invoice'  => 'Hóa đơn',
        'amount'   => 'Tổng tiền',
        'created'  => 'Ngày tạo',
        'team'     => 'Đội',
        'employee' => 'Nhân viên',
        'range'    => ':from - :to',
        'from'     => 'từ :value',
        'to'       => 'đến :value',
    ],

    // Cột bảng
    'code'               => 'Mã đơn hàng',
    'customer'           => 'Khách hàng',
    'quotation'          => 'Báo giá',
    'payment_type_col'   => 'Hình thức',
    'total'              => 'Tổng tiền',
    'paid'               => 'Đã thanh toán',
    'remaining'          => 'Còn lại',
    'invoices'           => 'Hóa đơn',
    'invoice_count'      => ':count hóa đơn',
    'invoices_pending'   => ':count chờ thanh toán',
    'invoices_overdue'   => ':count quá hạn',
    'view_invoices'      => 'Xem hóa đơn của đơn :code',
    'view_quotation'     => 'Xem báo giá :code',
];
