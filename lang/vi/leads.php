<?php

declare(strict_types=1);

// Trang danh sách Lead (livewire/leads/index)
return [
    'title'                  => 'Danh sách Lead',
    'subtitle'               => 'Theo dõi Lead mới nhập và tình trạng phân chia cho đội kinh doanh',
    'subtitle_branch'        => 'Lead thuộc :branch',

    // Nút thao tác (chưa có chức năng)
    'add'                    => 'Thêm Lead',
    'import'                 => 'Import Excel',
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
