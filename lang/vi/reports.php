<?php

declare(strict_types=1);

// Dashboard & Báo cáo
return [
    'other'         => 'Khác',
    'not_converted' => 'Chưa chuyển đổi',
    'week_label'    => 'Tuần :week/:year',
    'all'           => 'Tất cả',
    'all_branches'  => 'Tất cả chi nhánh',
    'from'          => 'Từ ngày',
    'to'            => 'Đến ngày',
    'branch'        => 'Chi nhánh',
    'method'        => 'Kênh liên hệ',
    'reset'         => 'Xoá lọc',
    'loading'       => 'Đang tải…',
    'no_data'       => 'Không có dữ liệu trong phạm vi lọc.',
    'show_table'    => 'Xem bảng số liệu',

    // Chiều phân tích
    'dimension' => [
        'source' => 'Nguồn Lead',
        'method' => 'Kênh liên hệ',
        'branch' => 'Chi nhánh',
        'status' => 'Trạng thái Lead',
        'month'  => 'Tháng',
        'week'   => 'Tuần',
    ],

    // Chỉ số
    'metric' => [
        'leads'            => 'Số Lead',
        'converted'        => 'Lead đã chuyển đổi',
        'won'              => 'Lead chốt thành công',
        'lost'             => 'Lead thất bại',
        'conversion_rate'  => 'Tỷ lệ chuyển đổi (%)',
        'win_rate'         => 'Tỷ lệ chốt / Lead (%)',
        'close_rate'       => 'Tỷ lệ thắng / Cơ hội đã đóng (%)',
        'revenue'          => 'Doanh thu',
        'paid'             => 'Đã thu',
        'expected'         => 'Giá trị kỳ vọng',
        'revenue_per_lead' => 'Doanh thu / Lead',
    ],

    'unit' => [
        'count'   => 'Số lượng',
        'percent' => 'Tỷ lệ',
        'money'   => 'Giá trị (₫)',
    ],

    // Phân tích chất lượng nguồn Lead
    'lead_sources' => [
        'title'    => 'Phân tích chất lượng nguồn Lead',
        'subtitle' => 'So sánh số lượng, khả năng chuyển đổi và doanh thu mang lại của từng nguồn Lead',
        'filters'  => 'Bộ lọc báo cáo',

        'fixed'     => 'Tổng quan nguồn Lead',
        'fixed_sub' => 'Các biểu đồ chuẩn, cập nhật theo bộ lọc phía trên.',

        'kpi' => [
            'leads'      => 'Tổng Lead',
            'sources'    => 'Số nguồn',
            'conversion' => 'Tỷ lệ chuyển đổi',
            'win'        => 'Tỷ lệ chốt',
            'revenue'    => 'Doanh thu',
            'leads_hint'      => ':count Lead đã chuyển đổi',
            'conversion_hint' => 'Lead → Cơ hội',
            'win_hint'        => ':count Lead chốt thành công',
            'revenue_hint'    => 'Đã thu :paid',
        ],

        'chart' => [
            'volume'       => 'Số Lead theo nguồn',
            'volume_sub'   => 'Đã chuyển thành Cơ hội và chưa chuyển',
            'rates'        => 'Tỷ lệ chuyển đổi theo nguồn',
            'rates_sub'    => 'Lead → Cơ hội và Lead → chốt thành công',
            'revenue'      => 'Cơ cấu doanh thu theo nguồn',
            'revenue_sub'  => 'Tổng giá trị đơn hàng từ Lead của từng nguồn',
            'revenue_none' => 'Chưa có đơn hàng nào trong phạm vi lọc.',
            'trend'        => 'Xu hướng Lead theo :period',
            'trend_sub'    => 'Số Lead mới của từng nguồn theo thời gian',
            'period_month' => 'tháng',
            'period_week'  => 'tuần',
        ],

        'table' => [
            'title'  => 'Bảng xếp hạng chất lượng nguồn',
            'sub'    => 'Sắp theo số Lead. Tỷ lệ thắng tính trên các Cơ hội đã có kết quả (Won + Lost).',
            'source' => 'Nguồn',
            'total'  => 'Tổng cộng',
        ],
    ],

    // Biểu đồ linh hoạt
    'builder' => [
        'title'        => 'Biểu đồ tuỳ chọn',
        'sub'          => 'Chọn loại biểu đồ, chiều phân tích, chỉ số và các giá trị cần vẽ rồi bấm "Vẽ biểu đồ".',
        'step_type'    => '1. Loại biểu đồ',
        'step_data'    => '2. Dữ liệu',
        'step_values'  => '3. Giá trị cần vẽ',
        'dimension'    => 'Phân tích theo',
        'breakdown'    => 'Chia nhóm theo',
        'no_breakdown' => '— Không chia nhóm —',
        'metrics'      => 'Chỉ số',
        'metrics_hint' => 'Chọn 1 hoặc nhiều chỉ số cùng đơn vị. Biểu đồ tròn / có chia nhóm chỉ dùng 1 chỉ số.',
        'sort'         => 'Sắp xếp',
        'limit'        => 'Hiển thị',
        'limit_all'    => 'Tất cả',
        'limit_top'    => 'Top :count',
        'values_all'   => 'Tất cả giá trị',
        'values_hint'  => 'Không chọn giá trị nào = vẽ tất cả.',
        'values_time'  => 'Chiều thời gian: dùng bộ lọc Từ ngày / Đến ngày ở trên để giới hạn khoảng thời gian.',
        'draw'         => 'Vẽ biểu đồ',
        'pending'      => 'Lựa chọn đã thay đổi – bấm "Vẽ biểu đồ" để cập nhật.',
        'round_note'   => 'Biểu đồ tròn không chia nhóm.',

        'type' => [
            'bar'      => 'Cột',
            'bar_h'    => 'Thanh ngang',
            'stacked'  => 'Cột chồng',
            'line'     => 'Đường',
            'area'     => 'Vùng',
            'pie'      => 'Tròn',
            'doughnut' => 'Vành khuyên',
        ],

        'sorts' => [
            'value_desc' => 'Giá trị giảm dần',
            'value_asc'  => 'Giá trị tăng dần',
            'label'      => 'Theo tên',
        ],

        'err_invalid'       => 'Lựa chọn không hợp lệ, vui lòng chọn lại.',
        'err_no_metric'     => 'Vui lòng chọn ít nhất 1 chỉ số.',
        'err_unit'          => 'Các chỉ số trên cùng 1 biểu đồ phải cùng đơn vị (số lượng, tỷ lệ hoặc giá trị tiền).',
        'err_round_one'     => 'Biểu đồ tròn / vành khuyên chỉ vẽ được 1 chỉ số.',
        'err_round_percent' => 'Biểu đồ tròn / vành khuyên không dùng cho chỉ số tỷ lệ – hãy chọn biểu đồ cột hoặc đường.',
        'err_breakdown_one' => 'Khi chia nhóm chỉ được chọn 1 chỉ số.',
        'err_stack_rate'    => 'Cột chồng chỉ dùng cho chỉ số cộng dồn được (số lượng, doanh thu).',
        'err_stack_need'    => 'Cột chồng cần chia nhóm hoặc chọn từ 2 chỉ số trở lên.',
    ],
];
