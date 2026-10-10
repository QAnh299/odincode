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


    'performance'        => 'Kết quả kinh doanh',
    'performance_note'   => 'Tính trên toàn bộ dữ liệu do nhân viên phụ trách',
    'opportunities'      => 'Opportunity phụ trách',
    'in_progress'        => ':count đang xử lý',
    'won'                => 'Opportunity đã chốt',
    'win_rate'           => 'Tỷ lệ chốt :rate',
    'quotations'         => 'Báo giá đã lập',
    'confirmed'          => ':count đã xác nhận',
    'appointments'       => 'Lịch hẹn',

    // Đội kinh doanh
    'teams' => [
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
