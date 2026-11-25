<?php

namespace App\Livewire\Reports\Concerns;

use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Renderless;

/**
 * Bấm vào 1 số liệu (biểu đồ, thẻ KPI, ô bảng) → popup danh sách bản ghi tạo nên số đó.
 * Giao diện: livewire.reports.partials.report-drill; biểu đồ: spec['drills'][dãy][điểm] (resources/js/charts.js).
 *
 * Component dùng trait khai báo:
 *   drillKinds()  [loại danh sách => [cột => kiểu]] - kiểu: text | date | money | int | decimal
 *   drillList()   [dòng, phạm vi (mảng chữ)]; mỗi dòng có thể kèm _lead, _opportunity, _order, _student để tạo liên kết
 * Tham số do trình duyệt gửi lên nên drillList() phải luôn giữ bộ lọc / phạm vi vai trò của trang.
 */
trait ReportDrill
{
    // Số dòng hiện mỗi lần trong popup
    public static int $drillStep = 50;

    abstract protected function drillKinds(): array;

    abstract protected function drillList(string $kind, array $params): array;

    /**
     * HTML danh sách cho popup. Renderless: không tính lại cả báo cáo mỗi lần mở / xem thêm.
     */
    #[Renderless]
    public function drillHtml(array $params, int $pages = 1): string
    {
        $drill = $this->drill($params, $pages);

        return $drill ? view('livewire.reports.partials.report-drill-list', ['drill' => $drill])->render() : '';
    }

    /**
     * Danh sách của số vừa bấm: tiêu đề, phạm vi, cột, các dòng hiện ra, tổng số dòng, tổng các cột tiền.
     */
    protected function drill(array $params, int $pages = 1): ?array
    {
        $kind = $this->drillText($params, 'kind') ?? '';
        $columns = $this->drillKinds()[$kind] ?? null;
        if ($columns === null) {
            return null;
        }

        [$rows, $scope] = $this->drillList($kind, $params);
        $sums = [];
        foreach ($columns as $column => $type) {
            if ($type === 'money') {
                $sums[$column] = array_sum(array_column($rows, $column));
            }
        }

        return [
            'title'   => __('reports.drill.kind.'.$kind),
            'scope'   => implode(' · ', array_filter($scope)),
            'columns' => $columns,
            'rows'    => $this->linkDrillRows(array_slice($rows, 0, max(1, $pages) * static::$drillStep)),
            'total'   => count($rows),
            'sums'    => $sums,
        ];
    }

    /**
     * Gắn liên kết tới trang chi tiết mà người xem mở được: đơn hàng / học viên, rồi cơ hội, rồi Lead.
     */
    protected function linkDrillRows(array $rows): array
    {
        $employee = Auth::user()->employee;
        $prefix = (string) Auth::user()->role_name;
        $ids = fn (string $key) => array_values(array_unique(array_filter(array_column($rows, $key))));

        $opportunityIds = $ids('_opportunity');
        $leadIds = $ids('_lead');
        $visible = fn () => Opportunity::query()->visibleTo($employee)->whereKey($opportunityIds);
        $opportunities = $opportunityIds ? $visible()->pluck('opportunity_id')->flip()->all() : [];
        $assigned = $opportunityIds ? $visible()->assigned()->pluck('opportunity_id')->flip()->all() : [];
        $leads = $leadIds ? Lead::query()->visibleTo($employee)->whereKey($leadIds)->pluck('lead_id')->flip()->all() : [];

        return array_map(function (array $row) use ($prefix, $opportunities, $assigned, $leads) {
            $opportunity = $row['_opportunity'] ?? '';
            $lead = $row['_lead'] ?? '';
            $candidates = [
                'orders.show'        => isset($opportunities[$opportunity]) ? $row['_order'] ?? null : null,
                'students.show'      => isset($opportunities[$opportunity]) ? $row['_student'] ?? null : null,
                'opportunities.show' => isset($assigned[$opportunity]) ? $opportunity : null,
                'leads.show'         => isset($leads[$lead]) ? $lead : null,
            ];
            $row['_url'] = null;
            foreach ($candidates as $name => $id) {
                if ($id !== null && Route::has("$prefix.$name")) {
                    $row['_url'] = route("$prefix.$name", $id);
                    break;
                }
            }

            return $row;
        }, $rows);
    }

    /**
     * Giá trị chữ trong tham số (không hợp lệ → null).
     */
    protected function drillText(array $params, string $key): ?string
    {
        return is_string($params[$key] ?? null) && $params[$key] !== '' ? $params[$key] : null;
    }

    protected function drillMonth(array $params): ?string
    {
        $month = $this->drillText($params, 'month');

        return $month !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : null;
    }

    protected function drillPeriodLabel(?string $from, ?string $to): ?string
    {
        $format = fn (?string $d) => $d ? date('d/m/Y', strtotime($d)) : '…';

        return $from || $to ? $format($from).' - '.$format($to) : null;
    }
}
