<?php

declare(strict_types=1);

// Trang hóa đơn: danh sách (livewire/invoices/index)
return [
    'title'              => 'Quản lý hóa đơn',
    'subtitle'           => 'Tra cứu hóa đơn của các đơn hàng: số tiền, hạn thanh toán, phương thức và tình trạng thanh toán.',

    // Tìm kiếm
    'search'             => 'Tìm kiếm',
    'search_placeholder' => 'Tìm theo mã hóa đơn, khách hàng, SĐT, email, mã đơn hàng, báo giá, nhân viên, kế toán, khóa học, số tiền…',
    'search_hint'        => 'Nhiều từ khóa · "cụm chính xác" · -loại trừ',
    'search_help_title'  => 'Cách tìm kiếm',
    'search_help'        => [
        'Nhiều từ khóa: hóa đơn phải khớp <strong>tất cả</strong>, VD <code>nguyen tra gop</code>',
        'Cụm chính xác: đặt trong ngoặc kép, VD <code>"Bảo Châu"</code>',
        'Loại trừ: thêm dấu trừ, VD <code>-INV001</code>',
        'Không phân biệt hoa/thường và dấu: <code>chuyen khoan</code> tìm ra "Chuyển khoản"',
        'Số tiền: gõ đủ số, VD <code>5400000</code> hoặc <code>5.400.000</code>',
        'Tìm trong: mã hóa đơn, trạng thái, phương thức, hình thức, mã đơn hàng / báo giá / cơ hội, tên/SĐT/email khách, nhân viên lập, kế toán, khóa học, số tiền',
    ],

    // Thẻ thống kê + tổng tiền
    'all'                => 'Tất cả',
    'collected'          => 'Đã thu',
    'outstanding'        => 'Chờ thu',
    'totals_label'       => 'Tổng tiền theo bộ lọc hiện tại',

    // Bộ lọc
    'filters'            => 'Bộ lọc',
    'close'              => 'Đóng',
    'active_filters'     => 'Đang lọc',
    'remove_filter'      => 'Bỏ bộ lọc',
    'all_employees'      => 'Tất cả nhân viên',
    'all_teams'          => 'Tất cả đội',
    'status_label'       => 'Trạng thái',
    'method_label'       => 'Phương thức',
    'deadline_label'     => 'Tình trạng hạn',
    'deadline_hint'      => 'Chỉ xét hóa đơn chưa thanh toán',
    'payment_type_label' => 'Hình thức thanh toán (đơn hàng)',
    'amount_range'       => 'Số tiền (VNĐ)',
    'amount_from'        => 'Số tiền từ',
    'amount_to'          => 'Số tiền đến',
    'issued_range'       => 'Ngày lập',
    'issued_from'        => 'Ngày lập từ',
    'issued_to'          => 'Ngày lập đến',
    'due_range'          => 'Hạn thanh toán',
    'due_from'           => 'Hạn từ',
    'due_to'             => 'Hạn đến',
    'paid_range'         => 'Ngày thanh toán',
    'paid_from'          => 'Thanh toán từ',
    'paid_to'            => 'Thanh toán đến',
    'team'               => 'Đội bán hàng',
    'employee'           => 'Nhân viên lập',
    'cash_pending'       => 'Tiền mặt chờ kế toán duyệt',
    'cash_pending_hint'  => 'Hóa đơn tiền mặt đang chờ thanh toán (kế toán đối chiếu và duyệt)',
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
    'result_count'       => 'Tìm thấy :count hóa đơn',
    'empty'              => 'Không tìm thấy hóa đơn phù hợp',
    'empty_sub'          => 'Thử đổi từ khoá hoặc bỏ bớt bộ lọc.',
    'empty_none'         => 'Chưa có hóa đơn nào',
    'empty_none_sub'     => 'Hóa đơn được tạo từ đơn hàng sau khi báo giá được xác nhận.',

    'sort' => [
        'newest'      => 'Ngày lập mới nhất',
        'oldest'      => 'Ngày lập cũ nhất',
        'due'         => 'Hạn thanh toán gần nhất',
        'amount_desc' => 'Số tiền cao → thấp',
        'amount_asc'  => 'Số tiền thấp → cao',
        'code'        => 'Mã hóa đơn (A → Z)',
        'customer'    => 'Tên khách hàng (A → Z)',
    ],

    // Trạng thái hóa đơn (Invoice::STATUSES)
    'status' => [
        'Unpaid'  => 'Chờ thanh toán',
        'Paid'    => 'Đã thanh toán',
        'Overdue' => 'Quá hạn',
    ],

    // Phương thức thanh toán (Invoice::METHODS; none = chưa chọn)
    'method' => [
        'BankTransfer' => 'Chuyển khoản',
        'Cash'         => 'Tiền mặt',
        'none'         => 'Chưa chọn',
    ],

    // Hình thức thanh toán của đơn hàng (SalesOrder::PAYMENT_TYPES)
    'payment_type' => [
        'Full'        => 'Toàn bộ',
        'Installment' => 'Trả góp',
    ],

    // Tình trạng hạn (chỉ hóa đơn chưa thanh toán)
    'deadline' => [
        'valid'   => 'Còn hạn',
        'soon'    => 'Sắp đến hạn (≤ 3 ngày)',
        'overdue' => 'Quá hạn',
    ],

    // Nhãn nhóm trên chip bộ lọc đang áp dụng
    'chip' => [
        'search'       => 'Từ khóa',
        'statuses'     => 'Trạng thái',
        'methods'      => 'Phương thức',
        'deadline'     => 'Hạn',
        'payment_type' => 'Hình thức',
        'amount'       => 'Số tiền',
        'issued'       => 'Ngày lập',
        'due'          => 'Hạn thanh toán',
        'paid'         => 'Ngày thanh toán',
        'team'         => 'Đội',
        'employee'     => 'Nhân viên',
        'range'        => ':from – :to',
        'from'         => 'từ :value',
        'to'           => 'đến :value',
    ],

    // Cột bảng
    'code'               => 'Mã hóa đơn',
    'installment'        => 'Kỳ :number',
    'customer'           => 'Khách hàng',
    'order'              => 'Đơn hàng',
    'amount'             => 'Số tiền',
    'issued_date'        => 'Ngày lập',
    'due_date'           => 'Hạn thanh toán',
    'accountant'         => 'Kế toán: :name',
    'days_left'          => 'còn :days ngày',
    'days_late'          => 'trễ :days ngày',
];
