<?php

declare(strict_types=1);

// Trang nhân viên: danh sách (livewire/employees/index), chi tiết (livewire/employees/show)
return [
    'title'              => 'Quản lý nhân viên',
    'subtitle'           => 'Tra cứu thông tin nhân viên, vai trò, đội kinh doanh và chi nhánh',
    'subtitle_team'      => 'Nhân viên trong đội :team',
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
    'no_team_note'       => 'Nhân viên này không thuộc đội kinh doanh nào.',
    'team_leader'        => 'Trưởng nhóm',
    'established_date'   => 'Ngày thành lập',
    'members'            => 'Thành viên',
    'teammates'          => 'Thành viên cùng đội',
    'no_teammates'       => 'Chưa có thành viên nào khác trong đội.',

    'account'            => 'Tài khoản đăng nhập',
    'username'           => 'Tên đăng nhập',
    'account_status'     => 'Trạng thái',
    'account_active'     => 'Đang hoạt động',
    'account_inactive'   => 'Đã khoá',
    'account_created'    => 'Ngày tạo',
    'no_account'         => 'Nhân viên chưa được cấp tài khoản.',

    'performance'        => 'Kết quả kinh doanh',
    'performance_note'   => 'Tính trên toàn bộ dữ liệu do nhân viên phụ trách',
    'opportunities'      => 'Opportunity phụ trách',
    'in_progress'        => ':count đang xử lý',
    'won'                => 'Opportunity đã chốt',
    'win_rate'           => 'Tỷ lệ chốt :rate',
    'quotations'         => 'Báo giá đã lập',
    'confirmed'          => ':count đã xác nhận',
    'appointments'       => 'Lịch hẹn',
];
