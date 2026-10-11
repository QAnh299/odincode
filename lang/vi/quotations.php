<?php

declare(strict_types=1);

// Trang báo giá: danh sách (livewire/quotations/index)
return [
    'title'              => 'Quản lý báo giá',

    // Tìm kiếm
    'search'             => 'Tìm kiếm',
    'search_placeholder' => 'Tìm theo mã báo giá, khách hàng, SĐT, email, nhân viên, khóa học, voucher, mã đơn hàng…',
    'search_hint'        => 'Gõ nhiều từ khóa để thu hẹp kết quả · "cụm từ" để tìm chính xác · -từ khóa để loại trừ',
    'search_help_title'  => 'Cách tìm kiếm',
    'search_help'        => [
        'Nhiều từ khóa: báo giá phải khớp <strong>tất cả</strong>, VD <code>nguyen ielts</code>',
        'Cụm chính xác: đặt trong ngoặc kép, VD <code>"Bảo Châu"</code>',
        'Loại trừ: thêm dấu trừ, VD <code>-VCH001</code>',
        'Không phân biệt hoa/thường và dấu: <code>tieng anh</code> tìm ra "Tiếng Anh"',
        'Tìm trong: mã báo giá, trạng thái, ghi chú, mã cơ hội, tên/SĐT/email khách, nhân viên lập, mã đơn hàng, mã voucher, khóa học',
    ],

    // Bộ lọc
    'filters'            => 'Bộ lọc',
    'close'              => 'Đóng',
    'active_filters'     => 'Đang lọc',
    'remove_filter'      => 'Bỏ bộ lọc',
    'all'                => 'Tất cả',
    'all_employees'      => 'Tất cả nhân viên',
    'all_teams'          => 'Tất cả đội',
    'status_label'       => 'Trạng thái',
    'deadline_label'     => 'Tình trạng hạn',
    'team'               => 'Đội bán hàng',
    'created_range'      => 'Ngày tạo',
    'created_from'       => 'Ngày tạo từ',
    'created_to'         => 'Ngày tạo đến',
    'expiry_range'       => 'Ngày hết hạn',
    'expiry_from'        => 'Hết hạn từ',
    'expiry_to'          => 'Hết hạn đến',
    'invalid_range'      => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu',
    'sort_by'            => 'Sắp xếp',
    'reset'              => 'Xoá bộ lọc',
    'reset_all'          => 'Xoá tất cả bộ lọc',
    'reset_form'         => 'Đặt lại',
    'apply'              => 'Áp dụng',
    'pending'            => 'Bộ lọc đã thay đổi, bấm Áp dụng để cập nhật danh sách',
    'loading'            => 'Đang tải…',
    'result_count'       => 'Tìm thấy :count báo giá',
    'empty'              => 'Không tìm thấy báo giá phù hợp',
    'empty_sub'          => 'Thử đổi từ khoá hoặc bỏ bớt bộ lọc.',
    'empty_none'         => 'Chưa có báo giá nào',
    'empty_none_sub'     => 'Báo giá được lập từ cơ hội ở giai đoạn Chốt.',

    'sort' => [
        'newest'     => 'Mới tạo gần đây',
        'oldest'     => 'Tạo sớm nhất',
        'expiring'   => 'Sắp hết hạn trước',
        'code'       => 'Mã báo giá (A → Z)',
        'total_desc' => 'Tổng tiền cao → thấp',
        'total_asc'  => 'Tổng tiền thấp → cao',
        'customer'   => 'Tên khách hàng (A → Z)',
    ],

    // Trạng thái báo giá (Quotation::STATUSES)
    'status' => [
        'Draft'     => 'Nháp',
        'Confirmed' => 'Đã xác nhận',
        'Rejected'  => 'Từ chối',
    ],

    // Tình trạng hạn (chỉ báo giá Nháp)
    'deadline' => [
        'valid'   => 'Còn hạn',
        'soon'    => 'Sắp hết hạn (≤ 3 ngày)',
        'overdue' => 'Quá hạn',
    ],

    'order_state' => [
        'with'    => 'Đã có đơn hàng',
        'without' => 'Chưa có đơn hàng',
    ],

    // Nhãn nhóm trên chip bộ lọc đang áp dụng
    'chip' => [
        'search'   => 'Từ khóa',
        'statuses' => 'Trạng thái',
        'deadline' => 'Hạn',
        'order'    => 'Đơn hàng',
        'created'  => 'Ngày tạo',
        'expiry'   => 'Hết hạn',
        'team'     => 'Đội',
        'employee' => 'Nhân viên',
        'range'    => ':from – :to',
        'from'     => 'từ :value',
        'to'       => 'đến :value',
    ],

    // ── Trang chi tiết (livewire/quotations/show) ──
    'detail_title'       => 'Báo giá :code',

    'detail' => [
        'back'              => 'Quay lại danh sách',
        'expires_on'        => 'Hết hạn :date',
        // Thanh thao tác (HĐ7, HĐ10, HĐ12, HĐ16) – chưa có chức năng
        'actions'           => 'Thao tác',
        'edit'              => 'Chỉnh sửa',
        'export_pdf'        => 'Xuất PDF',
        'confirm'           => 'Xác nhận báo giá',
        'reject'            => 'Từ chối báo giá',
        'coming_soon'       => 'Chức năng sẽ có ở bước sau',
        'expired_warning'   => 'Báo giá đã quá hạn; không thể xuất PDF hoặc xác nhận',
        // Tiến trình
        'progress'          => 'Tiến trình',
        'step_quotation'    => 'Báo giá',
        'step_order'        => 'Đơn hàng',
        'step_invoice'      => 'Hóa đơn',
        'step_payment'      => 'Thanh toán',
        'none_yet'          => 'Chưa có',
        'invoice_count'     => ':count hóa đơn',
        'paid_of'           => ':paid / :total',
        'rejected_note'     => 'Báo giá đã bị từ chối nên không tạo đơn hàng',
        // Thông tin chung
        'general'           => 'Thông tin chung',
        'created_at'        => 'Ngày tạo',
        'updated_at'        => 'Cập nhật gần nhất',
        'expiry_date'       => 'Ngày hết hạn',
        'employee'          => 'Nhân viên lập',
        'notes'             => 'Ghi chú',
        'no_notes'          => 'Không có ghi chú',
        // Khách hàng, cơ hội
        'customer'          => 'Khách hàng',
        'full_name'         => 'Họ tên',
        'email'             => 'Email',
        'phone'             => 'Số điện thoại',
        'opportunity'       => 'Cơ hội',
        'opportunity_id'    => 'Mã cơ hội',
        'stage'             => 'Giai đoạn',
        'opportunity_state' => 'Trạng thái cơ hội',
        // Khóa học + tổng tiền
        'courses'           => 'Khóa học trong báo giá',
        'no_courses'        => 'Báo giá chưa có khóa học nào',
        'no'                => '#',
        'course_id'         => 'Mã khóa',
        'course_name'       => 'Tên khóa học',
        'unit_price'        => 'Đơn giá',
        'quantity'          => 'SL',
        'vat'               => 'VAT',
        'line_total'        => 'Thành tiền',
        'subtotal'          => 'Tạm tính',
        'discount'          => 'Voucher :code (−:value)',
        'grand_total'       => 'Tổng cộng',
        // Voucher
        'voucher'           => 'Voucher áp dụng',
        'voucher_code'      => 'Mã voucher',
        'voucher_type'      => 'Loại giảm giá',
        'voucher_value'     => 'Giá trị',
        'voucher_validity'  => 'Thời gian hiệu lực',
        'voucher_invalid'   => 'Voucher hiện không còn hiệu lực; số tiền giảm vẫn tính như khi lập báo giá',
        // Đơn hàng
        'order'             => 'Đơn hàng',
        'order_id'          => 'Mã đơn hàng',
        'order_created'     => 'Ngày tạo đơn',
        'order_status'      => 'Trạng thái đơn',
        'payment_type'      => 'Hình thức thanh toán',
        'order_total'       => 'Tổng đơn',
        'paid'              => 'Đã thanh toán',
        'remaining'         => 'Còn lại',
        'invoices'          => 'Số hóa đơn',
    ],

    // Trạng thái cơ hội (Opportunity::STATUSES)
    'opportunity_status' => [
        'InProgress' => 'Đang xử lý',
        'Won'        => 'Thành công',
        'Lost'       => 'Thất bại',
        'Archived'   => 'Lưu trữ',
    ],

    // Trạng thái đơn hàng (SalesOrders.status)
    'order_status' => [
        'InProgress' => 'Đang xử lý',
        'Completed'  => 'Hoàn tất',
    ],

    // Hình thức thanh toán (SalesOrders.payment_type)
    'payment_type' => [
        'Full'        => 'Toàn bộ',
        'Installment' => 'Trả góp',
        'none'        => 'Chưa xác định',
    ],

    // Cột bảng
    'code'               => 'Mã báo giá',
    'customer'           => 'Khách hàng',
    'employee'           => 'Nhân viên lập',
    'created_at'         => 'Ngày tạo',
    'expiry_date'        => 'Ngày hết hạn',
    'total'              => 'Tổng tiền',
    'order'              => 'Đơn hàng',
    'days_left'          => 'còn :days ngày',
    'overdue'            => 'Quá hạn',
];
