<?php

declare(strict_types=1);

// Trang nhân viên: danh sách (livewire/employees/index), chi tiết (livewire/employees/show)
// và đội kinh doanh (livewire/sales-teams/index)
return [
    'title'              => 'Quản lý nhân viên',
    'subtitle'           => 'Tra cứu thông tin nhân viên, vai trò, đội kinh doanh và chi nhánh',
    'subtitle_sale_admin' => 'Sale Leader và Salesperson thuộc quyền quản lý của bạn',
    'subtitle_team'      => 'Salesperson trong đội :team của bạn',
    'subtitle_no_team'   => 'Bạn chưa thuộc đội kinh doanh nào',
    'detail_title'       => 'Nhân viên :name',

    // Tra cứu
    'lookup'             => 'Tra cứu nhân viên',
    'search'             => 'Tìm kiếm',
    'search_placeholder' => 'Tìm theo mã, họ tên, email hoặc số điện thoại (VD: EMP004, Lan, 0901…)…',
    'all'                => 'Tất cả',
    'sort_by'            => 'Sắp xếp',
    'reset'              => 'Xoá bộ lọc',
    'loading'            => 'Đang tải…',
    'result_count'       => 'Tìm thấy :count nhân viên',
    'empty'              => 'Không tìm thấy nhân viên nào',
    'empty_sub'          => 'Thử đổi từ khoá hoặc bỏ bớt bộ lọc.',

    'sort' => [
        'code'   => 'Mã nhân viên (A → Z)',
        'name'   => 'Họ tên (A → Z)',
        'newest' => 'Vào làm gần đây',
        'oldest' => 'Vào làm lâu nhất',
    ],

    'state' => [
        'all'      => 'Tất cả nhân viên',
        'working'  => 'Đang làm việc',
        'resigned' => 'Đã nghỉ việc',
    ],

    // Cột bảng / trường
    'employee'           => 'Nhân viên',
    'role'               => 'Vai trò',
    'team'               => 'Đội',
    'no_team'            => 'Chưa thuộc đội',
    'branch'             => 'Chi nhánh',
    'contact'            => 'Liên hệ',
    'hire_date'          => 'Ngày vào làm',
    'status'             => 'Trạng thái',
    'actions'            => 'Thao tác',
    'view'               => 'Xem chi tiết',
    'leader_tag'         => 'Trưởng nhóm',

    // Chi tiết
    'back_to_list'       => 'Quay lại danh sách',
    'leader_of'          => 'Trưởng nhóm :team',
    'profile_title'      => 'Thông tin nhân viên',
    'seniority_value'    => 'Thâm niên :value',
    'team_since'         => 'Thành lập :date',
    'team_members'       => 'Thành viên trong đội',
    'personal_info'      => 'Thông tin cá nhân',
    'date_of_birth'      => 'Ngày sinh',
    'email'              => 'Email',
    'phone'              => 'Số điện thoại',
    'work_info'          => 'Thông tin công việc',
    'job_title'          => 'Chức danh',
    'seniority_label'    => 'Thâm niên',
    'seniority'          => ':years năm :months tháng',
    'seniority_years'    => ':years năm',
    'seniority_months'   => ':months tháng',
    'seniority_new'      => 'Dưới 1 tháng',

    'team_section'       => 'Đội kinh doanh',
    'view_team'          => 'Xem đội',
    'no_team_note'       => 'Nhân viên này không thuộc đội kinh doanh nào.',
    'team_leader'        => 'Trưởng nhóm',
    'established_date'   => 'Ngày thành lập',
    'members'            => 'Thành viên',
    'teammates'          => 'Thành viên cùng đội',
    'no_teammates'       => 'Chưa có thành viên nào khác trong đội.',


    // Kết quả kinh doanh (chi tiết Sale Leader / Salesperson) – cùng cách tính với báo cáo Phân tích hiệu quả Sales
    'perf' => [
        'title'          => 'Kết quả kinh doanh',
        'title_team'     => 'Kết quả kinh doanh của đội :team',
        'no_team'        => 'Sale Leader này chưa phụ trách đội kinh doanh nào nên chưa có số liệu.',
        'period'         => 'Kỳ xem',
        'month'          => 'Tháng :month',
        'all_time'       => 'Toàn bộ thời gian',
        'open_report'    => 'Xem báo cáo chi tiết',
        'kpi' => [
            'revenue'  => 'Doanh thu',
            'won'      => 'Lead chốt',
            'students' => 'Học viên mới',
            'assigned' => 'Lead được giao',
        ],
        'paid'           => 'Đã thu :amount',
        'win_rate'       => 'Tỷ lệ thắng :rate · :lost thất bại',
        'processed'      => 'Đã xử lý :rate',
        'target'         => 'Đạt :rate% chỉ tiêu (:target)',
        'target_reached' => 'Đạt :rate% chỉ tiêu',
        'delta'          => ':value% so với tháng trước',
        'delta_new'      => 'Tháng trước chưa có',
        'delta_new_short' => 'Mới',
        'delta_note'     => 'Nhãn % bên cạnh mỗi chỉ số: so với tháng trước.',
    ],

    // Đội kinh doanh
    'teams' => [
        'detail_title'       => 'Đội :name',
        'back_to_list'       => 'Danh sách đội kinh doanh',
        'view_detail'        => 'Xem chi tiết đội',
        'open_in_list'       => 'Xem trong danh sách nhân viên',
        'title'              => 'Đội kinh doanh',
        'subtitle'           => 'Danh sách các đội kinh doanh, trưởng nhóm và thành viên',
        'lookup'             => 'Tra cứu đội kinh doanh',
        'search_placeholder' => 'Tìm theo mã đội, tên đội, trưởng nhóm hoặc thành viên (VD: TEAM01, Huy)…',
        'result_count'       => 'Tìm thấy :count đội',
        'empty'              => 'Không tìm thấy đội nào',
        'team'               => 'Đội',
        'no_leader'          => 'Chưa có trưởng nhóm',
        'member_count'       => ':count thành viên',
        'view_members'       => 'Xem nhân viên',

        // Popup Tạo đội / Chỉnh sửa đội / Gắn trưởng nhóm (Sale Admin)
        'form' => [
            'create'           => 'Tạo đội',
            'create_sub'       => 'Mã đội được tạo tự động (VD: TEAM03). Có thể gắn trưởng nhóm ngay hoặc để sau.',
            'edit'             => 'Chỉnh sửa đội',
            'edit_sub'         => 'Cập nhật tên đội, ngày thành lập, trưởng nhóm và các Salesperson thuộc đội.',
            'team_name'        => 'Tên đội',
            'placeholder_name' => 'VD: ODIN Sales 3',
            'no_leader_yet'    => '— Chưa gắn trưởng nhóm —',
            'current'          => 'trưởng nhóm hiện tại',
            'in_team'          => 'đang ở :team',
            'leader_hint'      => 'Đổi trưởng nhóm: người mới được chuyển vào đội, trưởng nhóm cũ được gỡ khỏi đội. Đã chọn thành viên thì chỉ chọn được trưởng nhóm cùng chi nhánh.',
            'no_candidates'    => 'Không còn Sale Leader nào trống: mỗi Sale Leader chỉ phụ trách một đội.',
            'required_hint'    => 'Trường có dấu * là bắt buộc.',
            'name_taken'       => 'Tên đội này đã tồn tại.',
            'date_future'      => 'Ngày thành lập không được sau hôm nay.',
            'leader_invalid'   => 'Trưởng nhóm phải là Sale Leader đang làm việc, chưa phụ trách đội khác và cùng chi nhánh với đội.',
            'members'          => 'Thành viên (Salesperson)',
            'selected'         => '· đã chọn :count',
            'member_search'    => 'Lọc theo tên hoặc mã nhân viên…',
            'members_hint'     => 'Đánh dấu để gắn vào đội, bỏ đánh dấu để gỡ khỏi đội. Mỗi nhân viên chỉ thuộc một đội và đội chỉ thuộc một chi nhánh: người đang ở đội khác hoặc khác chi nhánh với trưởng nhóm không chọn được.',
            'no_salespeople'   => 'Chưa có Salesperson nào đang làm việc.',
            'member_invalid'   => 'Có nhân viên không hợp lệ, đang thuộc đội khác hoặc khác chi nhánh với đội.',
            'member_mixed_branch' => 'Thành viên của một đội phải cùng một chi nhánh.',
            'team_branch'      => 'Chi nhánh của đội',
            'branch_undecided' => 'Chưa xác định (theo trưởng nhóm hoặc thành viên được chọn)',
            'create_branch_hint' => 'Mỗi đội chỉ thuộc một chi nhánh: chi nhánh của đội là chi nhánh của trưởng nhóm, thành viên phải cùng chi nhánh.',
            'no_candidates_branch' => 'Không còn Sale Leader nào trống ở chi nhánh :branch (mỗi Sale Leader chỉ phụ trách một đội).',
            'save'             => 'Lưu',
            'cancel'           => 'Huỷ',
            'close'            => 'Đóng',
            'saved_create'     => 'Đã tạo đội :name (:id).',
            'saved_edit'       => 'Đã cập nhật đội :name.',
        ],

        'summary' => [
            'teams'   => 'Số đội kinh doanh',
            'in_team' => 'Nhân viên kinh doanh trong đội',
            'without' => 'Nhân viên kinh doanh chưa có đội',
        ],

        'sort' => [
            'name'    => 'Tên đội (A → Z)',
            'newest'  => 'Thành lập gần đây',
            'oldest'  => 'Thành lập lâu nhất',
            'members' => 'Nhiều thành viên nhất',
        ],
    ],
];
