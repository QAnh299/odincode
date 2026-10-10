<?php

declare(strict_types=1);

// Trang danh sách Lead (livewire/leads/index)
return [
    'title'                  => 'Danh sách Lead',
    'subtitle'               => 'Theo dõi Lead mới nhập và tình trạng phân chia cho đội kinh doanh',
    'subtitle_branch'        => 'Lead thuộc :branch',

    // Nút thao tác (Phân chia chưa có chức năng)
    'add'                    => 'Thêm Lead',
    'import'                 => 'Import Excel',
    'close'                  => 'Đóng',
    'cancel'                 => 'Huỷ',

    // Tên trường – dùng cho popup Thêm Lead, tiêu đề cột file Excel mẫu và thông báo lỗi
    'field' => [
        'full_name'      => 'Họ và tên',
        'phone'          => 'Số điện thoại',
        'email'          => 'Email',
        'source_name'    => 'Nguồn Lead',
        'source_url'     => 'Link nguồn',
        'contact_method' => 'Kênh liên hệ',
        'branch_id'      => 'Mã chi nhánh',
    ],

    // Popup Thêm Lead
    'form' => [
        'title'              => 'Thêm Lead mới',
        'sub'                => 'Lead mới được tạo ở trạng thái "Mới", chờ phân chia cho đội kinh doanh.',
        'required_hint'      => 'Các trường có dấu * là bắt buộc.',
        'placeholder_name'   => 'VD: Nguyễn Văn A',
        'placeholder_phone'  => 'VD: 0912345678',
        'placeholder_email'  => 'VD: nguyenvana@example.com',
        'placeholder_source' => 'VD: Facebook, Website, Workshop…',
        'placeholder_url'    => 'https://…',
        'choose_method'      => '— Chọn kênh liên hệ —',
        'choose_branch'      => '— Chọn chi nhánh —',
        'save'               => 'Lưu Lead',
        'saving'             => 'Đang lưu…',
        'created'            => 'Đã thêm Lead :id – :name.',
        'phone_invalid'      => 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.',
        'method_invalid'     => 'Kênh liên hệ chỉ nhận một trong các giá trị: :values.',
        'branch_invalid'     => 'Mã chi nhánh không tồn tại.',
        'branch_not_allowed' => 'Bạn chỉ được thêm Lead cho chi nhánh của mình.',
    ],

    // Popup Import Excel + file mẫu + file kết quả
    'excel' => [
        'title'          => 'Import Lead từ Excel',
        'sub'            => 'Dòng hợp lệ sẽ được ghi nhận, dòng lỗi bị bỏ qua và ghi rõ lý do trong file kết quả.',
        'step_template'  => 'Tải file mẫu',
        'step_template_sub' => 'Nhập dữ liệu Lead vào sheet đầu tiên, giữ nguyên dòng tiêu đề. Xem sheet "Hướng dẫn" để biết giá trị hợp lệ.',
        'download_template' => 'Tải file mẫu (.xlsx)',
        'step_upload'    => 'Tải file lên',
        'step_upload_sub' => 'Chỉ nhận file .xlsx, tối đa 5MB và :max dòng.',
        'choose_file'    => 'Chọn file Excel',
        'uploading'      => 'Đang tải file lên…',
        'submit'         => 'Import',
        'processing'     => 'Đang kiểm tra dữ liệu…',
        'file_required'  => 'Vui lòng chọn file Excel.',
        'file_type'      => 'Chỉ chấp nhận file Excel định dạng .xlsx.',
        'file_size'      => 'File không được vượt quá 5MB.',

        'error_unreadable' => 'Không đọc được file. Vui lòng kiểm tra lại file Excel (.xlsx).',
        'error_template'   => 'File không đúng mẫu: dòng tiêu đề không khớp với file mẫu. Vui lòng tải file mẫu và nhập lại.',
        'error_empty'      => 'File không có dòng dữ liệu nào.',
        'error_too_many'   => 'File vượt quá :max dòng dữ liệu. Vui lòng chia nhỏ file.',

        'done_all'       => 'Import thành công toàn bộ :count Lead.',
        'done_partial'   => 'Đã import :success/:total Lead. :failed dòng bị lỗi – tải file kết quả để xem lý do.',
        'done_none'      => 'Import thất bại: cả :count dòng đều lỗi – tải file kết quả để xem lý do.',
        'result_title'   => 'Kết quả import',
        'result_total'   => 'Tổng dòng',
        'result_success' => 'Thành công',
        'result_failed'  => 'Lỗi',
        'download_result' => 'Tải file kết quả',
        'result_missing' => 'File kết quả không còn tồn tại, vui lòng import lại.',
        'import_another' => 'Import file khác',

        'sheet_data'     => 'Leads',
        'sheet_guide'    => 'Hướng dẫn',
        'sheet_result'   => 'Kết quả import',
        'col_line'       => 'Dòng trong file',
        'col_result'     => 'Kết quả',
        'col_lead_id'    => 'Mã Lead',
        'col_reason'     => 'Lý do lỗi',
        'status_ok'      => 'Thành công',
        'status_fail'    => 'Lỗi',

        'guide_column'   => 'Cột',
        'guide_note'     => 'Hướng dẫn nhập',
        'guide_methods'  => 'Kênh liên hệ hợp lệ',
        'guide_branches' => 'Mã chi nhánh hợp lệ',
        'guide' => [
            'full_name'      => 'Bắt buộc, tối đa 150 ký tự.',
            'phone'          => 'Bắt buộc, 10 chữ số, bắt đầu bằng 0 (được phép trùng với Lead đã có – học viên đăng ký thêm khoá học). Nên định dạng ô là Text để giữ số 0 đầu.',
            'email'          => 'Không bắt buộc, đúng định dạng email.',
            'source_name'    => 'Bắt buộc, tối đa 150 ký tự (VD: Facebook, Website, Workshop, Referral).',
            'source_url'     => 'Không bắt buộc, đường dẫn bắt đầu bằng http:// hoặc https://.',
            'contact_method' => 'Bắt buộc: Zalo, Phone hoặc Facebook.',
            'branch_id'      => 'Bắt buộc với Sale Admin (VD: BR001). Sale Leader có thể bỏ trống – hệ thống tự gán chi nhánh của mình.',
        ],
    ],
    'distribute_team'        => 'Phân chia Lead cho Sale Team',
    'distribute_salesperson' => 'Phân chia Lead cho Salesperson',

    // Thống kê
    'stat' => [
        'all'       => 'Tổng Lead',
        'New'       => 'Chưa phân chia',
        'Converted' => 'Đã chuyển đổi',
        'rate'      => 'Tỷ lệ chuyển đổi',
    ],

    // Bộ lọc
    'filters'                => 'Bộ lọc Lead',
    'search'                 => 'Tìm kiếm',
    'search_placeholder'     => 'Tìm theo mã, họ tên, SĐT hoặc email…',
    'all'                    => 'Tất cả',
    'all_branches'           => 'Tất cả chi nhánh',
    'from'                   => 'Từ ngày',
    'to'                     => 'Đến ngày',
    'reset'                  => 'Xoá bộ lọc',
    'loading'                => 'Đang tải…',
    'result_count'           => 'Tìm thấy :count Lead',
    'empty'                  => 'Không tìm thấy Lead nào',
    'empty_sub'              => 'Thử đổi từ khoá hoặc bỏ bớt bộ lọc.',

    // Cột bảng
    'code'                   => 'Mã Lead',
    'customer'               => 'Khách hàng',
    'phone'                  => 'Số điện thoại',
    'source'                 => 'Nguồn',
    'contact_method'         => 'Kênh liên hệ',
    'branch'                 => 'Chi nhánh',
    'created_at'             => 'Ngày tạo',
    'owner'                  => 'Phụ trách',
    'unassigned'             => 'Chưa phân chia',
    'status'                 => 'Trạng thái',

    'status_name' => [
        'New'       => 'Mới',
        'Converted' => 'Đã chuyển đổi',
    ],

    'method' => [
        'Zalo'     => 'Zalo',
        'Phone'    => 'Điện thoại',
        'Facebook' => 'Facebook',
    ],
];
