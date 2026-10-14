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

    // Phân tích hiệu quả Sales Team / Salesperson
    'sales_performance' => [
        'title'      => 'Hiệu quả Sales Team / Salesperson',
        'subtitle'   => 'Đánh giá theo tháng: mức đạt chỉ tiêu của từng đội, kết quả và cách xử lý Lead của từng salesperson',
        'month'      => 'Tháng đánh giá',
        'team'       => 'Đội kinh doanh',
        'all_teams'  => 'Tất cả đội',
        'employee'   => 'Salesperson',
        'sort'       => 'Xếp hạng salesperson theo',
        'resigned'   => 'đã nghỉ',
        'rank'       => 'Hạng',
        'members'    => 'Số salesperson',
        'attain'     => '% đạt',
        'no_target'  => 'chưa có chỉ tiêu',
        'total'      => 'Tổng cộng',
        'hours'      => ':value giờ',
        'days'       => ':value ngày',
        'no_data'    => 'Tháng :month chưa có số liệu trong phạm vi đang xem.',
        'delta_vs'   => 'so với :month',
        'delta_same' => 'Không đổi so với :month',
        'delta_new'  => 'Mới (:month chưa có)',

        'team_section'         => 'Hiệu quả team',
        'team_section_sub'     => 'So với chỉ tiêu tháng của đội. Xếp hạng theo % đạt chỉ tiêu doanh thu; đội chưa có chỉ tiêu xếp sau.',
        'employee_section'     => 'Hiệu quả salesperson',
        'employee_section_sub' => 'Xếp hạng theo :metric (bấm tiêu đề cột để đổi). Màu chữ so với trung bình đội của người đó:',
        'legend_up'            => 'tốt hơn trung bình đội',
        'legend_down'          => 'kém hơn trung bình đội',
        'chart_section'        => 'Biểu đồ',
        'chart_section_sub'    => 'Cập nhật theo tháng và phạm vi đang chọn.',

        'target' => [
            'revenue'  => 'Doanh thu / chỉ tiêu',
            'students' => 'Học viên mới / chỉ tiêu',
            'assigned' => 'Lead mới / chỉ tiêu',
        ],

        'kpi' => [
            'assigned'            => 'Lead được giao',
            'won'                 => 'Lead chốt',
            'revenue'             => 'Doanh thu',
            'response'            => 'Phản hồi đầu tiên TB',
            'contacts'            => 'Số lần liên hệ',
            'assigned_hint'       => ':count đã xử lý (:rate)',
            'won_hint'            => 'Tỷ lệ thắng :rate',
            'revenue_hint'        => 'Đã thu :paid',
            'revenue_target_hint' => 'Đạt :rate chỉ tiêu :target',
            'response_hint'       => 'Chốt TB sau :days',
            'contacts_hint'       => ':unreachable không liên hệ được · :backward chuyển ngược',
        ],

        'metric' => [
            'assigned'             => 'Lead được giao',
            'processed'            => 'Đã xử lý',
            'moved'                => 'Chuyển Stage',
            'won'                  => 'Lead chốt',
            'reached'              => 'Số Lead đạt tới stage',
            'target'               => 'Chỉ tiêu',
            'processed_rate'       => 'Tỷ lệ xử lý',
            'moved_rate'           => 'Tỷ lệ chuyển Stage',
            'close_rate'           => 'Tỷ lệ thắng',
            'contact_success_rate' => 'Liên hệ thành công',
            'revenue'              => 'Doanh thu',
            'paid'                 => 'Đã thu',
            'avg_response_hours'   => 'Phản hồi đầu TB',
            'avg_close_days'       => 'Thời gian chốt TB',
            'contacts'             => 'Lần liên hệ',
            'unreachable'          => 'Không liên hệ được',
            'backward'             => 'Chuyển ngược Stage',
        ],

        'chart' => [
            'team'              => 'Doanh thu so với chỉ tiêu theo đội',
            'team_sub'          => 'Doanh thu đơn hàng tạo trong tháng và chỉ tiêu doanh thu của đội',
            'funnel'            => 'Phễu chuyển đổi theo Stage',
            'funnel_sub'        => 'Lead được giao trong tháng: số Lead đã đạt tới từng stage và tỷ lệ sang stage kế tiếp',
            'pipeline'          => 'Xử lý Lead theo salesperson',
            'pipeline_sub'      => 'Lead được giao → đã xử lý → chuyển Stage; Lead chốt trong tháng',
            'revenue'           => 'Doanh thu theo salesperson',
            'revenue_sub'       => 'Giá trị đơn hàng tạo trong tháng và số tiền đã thu',
            'rates'             => 'Tỷ lệ hiệu quả theo salesperson',
            'rates_sub'         => 'Tỷ lệ xử lý, tỷ lệ thắng và tỷ lệ liên hệ thành công',
            'quality'           => 'Hoạt động liên hệ & chất lượng xử lý',
            'quality_sub'       => 'Số lần liên hệ, Lead không liên hệ được và Lead bị chuyển ngược Stage',
            'trend_count'       => 'Xu hướng 6 tháng – Lead',
            'trend_count_sub'   => 'Lead được giao, đã xử lý và chốt theo tháng',
            'trend_revenue'     => 'Xu hướng 6 tháng – Doanh thu',
            'trend_revenue_sub' => 'Doanh thu theo tháng so với tổng chỉ tiêu các đội đang xem',
        ],

        'stage_table' => [
            'title' => 'Tỷ lệ chuyển đổi từng Stage theo salesperson',
            'sub'   => 'Lead được giao trong tháng. Mỗi ô: số Lead đã đạt tới stage và tỷ lệ so với stage liền trước.',
        ],

        'method' => [
            'title'    => 'Cách tính các chỉ số',
            'assigned' => 'Lead được giao: Lead được chuyển thành Cơ hội và giao cho salesperson trong tháng (theo ngày giao).',
            'process'  => 'Chỉ số quy trình tính trên các Lead được giao trong tháng: đã xử lý = đã liên hệ hoặc đã rời stage đầu; chuyển Stage; phễu stage; phản hồi đầu = từ lúc giao đến lần liên hệ đầu tiên; lần liên hệ; không liên hệ được = đã liên hệ nhưng chưa lần nào thành công; chuyển ngược = có lần quay về stage trước.',
            'outcome'  => 'Lead chốt / thất bại tính theo ngày đóng Cơ hội trong tháng (lúc vào stage Chốt / Lưu trữ). Tỷ lệ thắng = chốt ÷ (chốt + thất bại). Thời gian chốt = từ lúc giao đến lúc chốt.',
            'revenue'  => 'Doanh thu = giá trị đơn hàng tạo trong tháng, tính cho người phụ trách Cơ hội; Đã thu = số tiền đã thanh toán của các đơn đó.',
            'target'   => 'Team so với chỉ tiêu tháng (Doanh thu, Học viên mới, Lead mới). Học viên mới tính theo ngày thành học viên.',
            'compare'  => 'Màu trong bảng salesperson: so với trung bình đội (số lượng = trung bình mỗi người, tỷ lệ / thời gian = mức chung của đội); chỉ so khi đội có từ 2 người có số liệu.',
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
