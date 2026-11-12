<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Models\Stage;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phân bổ Lead (quy trình PhanLead.docx):
 *
 *   1. Sale Admin → Sales Team: Lead 'New' của 1 chi nhánh được chuyển thành Cơ hội ở stage đầu,
 *      người phụ trách = trưởng nhóm của đội (odin.sql không có cột lưu đội của Lead).
 *      Lead vẫn 'New' (đội đang giữ, chưa phân cho salesperson).
 *   2. Sale Leader → Salesperson: Cơ hội trưởng nhóm đang giữ (stage đầu, InProgress, chưa chăm sóc)
 *      được đổi người phụ trách sang salesperson, ngày giao (conversion_date) = lúc phân.
 *      Lúc này Lead mới chuyển sang 'Converted'.
 *
 * Chia tự động: chia đều phần nguyên, Lead dư lần lượt cho Top 2 theo hiệu suất tháng trước.
 * Hiệu suất = cộng hạng: xếp hạng riêng theo từng tiêu chí (doanh thu, tỷ lệ chốt, TB thời gian xử lý)
 * rồi cộng 3 thứ hạng; tổng hạng càng nhỏ càng tốt (không cần chuẩn hoá hay đặt trọng số).
 * Ngưỡng MAX_PER_SALESPERSON Cơ hội đang xử lý (InProgress)/salesperson (chỉ áp cho Salesperson, không áp cho đội)
 * chỉ là cảnh báo: chia tự động ưu tiên người còn chỗ, hết chỗ thì phần còn lại vẫn giao cho Top 2 hiệu suất;
 * có người vượt ngưỡng thì trưởng nhóm xác nhận (thấy rõ ai vượt bao nhiêu) trước khi ghi.
 *
 * Khách cũ quay lại (LeadDuplicateService::reopen đưa Lead về 'New') và người thân dùng chung SĐT / email
 * (anh chị em, phụ huynh đăng ký cho các con…): popup phân chia gợi ý đội / người đang hoặc từng phụ trách;
 * khi ghi kết quả, các Lead này được ưu tiên giao cho đội / người đó, người thân cùng lần phân được giao chung
 * (số lượng mỗi người nhận vẫn đúng kế hoạch) - xem suggestedOwners(), familiesIn(), matchPreferred().
 */
class LeadDistributionService
{
    const MAX_PER_SALESPERSON = 50;

    // Tiêu chí cộng hạng hiệu suất (tính theo tháng trước) → khoá chỉ số trong SalesPerformanceReport
    //   avg_close_days = TB số ngày từ lúc được giao Lead đến khi chốt thành công (Won)
    const CRITERIA = ['revenue', 'close_rate', 'avg_close_days'];

    // Tiêu chí càng nhỏ càng tốt
    const LOWER_IS_BETTER = ['avg_close_days'];

    // Cỡ mẫu tối thiểu để tiêu chí tỷ lệ / trung bình đáng tin (tránh 1/1 = 100%, 1 đơn chốt trong 1 ngày):
    //   close_rate     → số Cơ hội đã đóng (Won + Lost) trong tháng
    //   avg_close_days → số Cơ hội chốt thành công (Won) trong tháng
    // Chưa đủ thì coi như không có số liệu (xếp cuối). Doanh thu là số tuyệt đối nên không cần ngưỡng.
    const MIN_SAMPLE = ['close_rate' => 15, 'avg_close_days' => 4];

    // Số đội / salesperson nhận Lead dư
    const TOP = 2;

    public function __construct(protected SalesPerformanceReport $report) {}

    /**
     * Tháng trước (tháng hiện tại chưa kết thúc nên chưa đủ số liệu): [from, to, nhãn m/Y].
     */
    public function previousMonth(): array
    {
        $start = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString(), $start->format('m/Y')];
    }

    /**
     * Hiệu suất tháng trước theo đội ('team') hoặc nhân viên ('employee'):
     * [id => ['revenue' => float, 'close_rate' => ?float, 'avg_close_days' => ?float, 'closed' => int, 'won' => int]].
     */
    public function performance(string $dimension, ?string $teamId = null): array
    {
        [$from, $to] = $this->previousMonth();

        $rows = $this->report->aggregate(array_filter(['from' => $from, 'to' => $to, 'team' => $teamId]), $dimension);

        return array_map(fn ($row) => [
            'revenue'        => $row['revenue'],
            'close_rate'     => $row['close_rate'],
            'avg_close_days' => $row['avg_close_days'],
            'closed'         => $row['won'] + $row['lost'],
            'won'            => $row['close_n'],
        ], $rows);
    }

    /**
     * Cỡ mẫu của tiêu chí: [đã có, tối thiểu]; tiêu chí không cần ngưỡng → null.
     */
    public function sample(?array $performance, string $criterion): ?array
    {
        $min = self::MIN_SAMPLE[$criterion] ?? null;
        if ($min === null) {
            return null;
        }

        $count = (int) ($performance[$criterion === 'close_rate' ? 'closed' : 'won'] ?? 0);

        return [$count, $min];
    }

    /**
     * Giá trị tiêu chí dùng để xếp hạng; null = không có số liệu hoặc chưa đủ cỡ mẫu tối thiểu.
     */
    public function score(?array $performance, string $criterion): ?float
    {
        $value = $performance[$criterion] ?? null;
        $sample = $this->sample($performance, $criterion);

        return $value === null || ($sample && $sample[0] < $sample[1]) ? null : (float) $value;
    }

    /**
     * Bảng cộng hạng trong nhóm $ids: [id => ['revenue' => hạng, 'close_rate' => hạng, 'avg_close_days' => hạng, 'total' => tổng hạng]].
     * Mỗi tiêu chí xếp hạng riêng (TB thời gian xử lý: nhanh hơn là tốt hơn); bằng giá trị thì đồng hạng (1, 1, 3);
     * không có / chưa đủ số liệu thì cùng xếp cuối ở tiêu chí đó.
     *
     * @param  string[]  $ids
     * @return array<string, array<string, int>>
     */
    public function scoreboard(array $ids, array $performance): array
    {
        $board = array_fill_keys($ids, ['total' => 0]);

        foreach (self::CRITERIA as $criterion) {
            $lower = in_array($criterion, self::LOWER_IS_BETTER, true);
            $values = [];
            foreach ($ids as $id) {
                $value = $this->score($performance[$id] ?? null, $criterion);
                $values[$id] = $value === null ? -INF : ($lower ? -$value : $value);
            }

            foreach ($values as $id => $value) {
                $rank = 1 + count(array_filter($values, fn ($other) => $other > $value));
                $board[$id][$criterion] = $rank;
                $board[$id]['total'] += $rank;
            }
        }

        return $board;
    }

    /**
     * Xếp hạng từ tốt đến kém theo tổng hạng (nhỏ hơn là tốt hơn);
     * hoà thì ai đang giữ ít Lead hơn trước, cuối cùng theo mã.
     *
     * @param  string[]  $ids
     * @return string[]
     */
    public function rank(array $ids, array $performance, array $load = []): array
    {
        $board = $this->scoreboard($ids, $performance);

        usort($ids, fn ($a, $b) => [$board[$a]['total'], $load[$a] ?? 0, $a] <=> [$board[$b]['total'], $load[$b] ?? 0, $b]);

        return $ids;
    }

    /**
     * Chia đều $total cho các id (đã xếp hạng); phần dư lần lượt cho Top 2.
     *
     * @param  string[]  $rankedIds
     * @return array<string, int>
     */
    public function splitEvenly(int $total, array $rankedIds): array
    {
        $count = count($rankedIds);
        if ($count === 0) {
            return [];
        }

        $plan = array_fill_keys($rankedIds, intdiv($total, $count));
        $top = array_slice($rankedIds, 0, min(self::TOP, $count));

        for ($i = 0; $i < $total % $count; $i++) {
            $plan[$top[$i % count($top)]]++;
        }

        return $plan;
    }

    /**
     * Số Cơ hội đang xử lý (InProgress) của từng nhân viên.
     *
     * @return array<string, int>
     */
    public function currentLoad(array $employeeIds): array
    {
        return Opportunity::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'InProgress')
            ->groupBy('employee_id')
            ->selectRaw('employee_id, COUNT(*) AS total')
            ->pluck('total', 'employee_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Số Lead còn nhận thêm được của salesperson.
     */
    public function capacity(string $employeeId, array $load): int
    {
        return max(0, self::MAX_PER_SALESPERSON - ($load[$employeeId] ?? 0));
    }

    /**
     * Cắt phần vượt ngưỡng 50 Lead/người: [kế hoạch đã cắt, tổng phần vượt].
     */
    public function excess(array $plan, array $load): array
    {
        $over = 0;
        foreach ($plan as $id => $count) {
            $allowed = min($count, $this->capacity($id, $load));
            $over += $count - $allowed;
            $plan[$id] = $allowed;
        }

        return [$plan, $over];
    }

    /**
     * Chia tự động có ngưỡng: phần vượt được phân lại lần lượt cho Top 2 salesperson còn chỗ
     * (theo thứ hạng hiệu suất); không ai còn chỗ dưới ngưỡng thì phần còn lại vẫn chia lần lượt cho Top 2
     * của nhóm được chọn (ngưỡng chỉ là cảnh báo, trưởng nhóm xác nhận trước khi ghi).
     *
     * @return array<string, int>
     */
    public function capAuto(array $plan, array $load, array $rankedIds): array
    {
        [$plan, $over] = $this->excess($plan, $load);

        $turn = 0;
        while ($over > 0) {
            $open = array_values(array_filter(
                $rankedIds,
                fn ($id) => ($plan[$id] ?? 0) < $this->capacity($id, $load),
            ));

            $top = array_slice($open ?: $rankedIds, 0, self::TOP);
            if (! $top) {
                break;
            }

            $plan[$top[$turn++ % count($top)]]++;
            $over--;
        }

        return $plan;
    }

    /**
     * Salesperson sẽ vượt ngưỡng sau lần phân này (chỉ người có nhận thêm Lead):
     * [employee_id => ['load' => đang quản lý, 'add' => nhận thêm, 'after' => sau khi phân, 'over' => số Lead vượt ngưỡng]].
     *
     * @param  array<string, int>  $plan
     * @param  array<string, int>  $load
     */
    public function overLimit(array $plan, array $load): array
    {
        $result = [];
        foreach ($plan as $id => $add) {
            $after = ($load[$id] ?? 0) + $add;
            if ($add > 0 && $after > self::MAX_PER_SALESPERSON) {
                $result[$id] = [
                    'load'  => $load[$id] ?? 0,
                    'add'   => $add,
                    'after' => $after,
                    'over'  => $after - self::MAX_PER_SALESPERSON,
                ];
            }
        }

        return $result;
    }

    /* ── Lead khả dụng ────────────────────────────────────────────────── */

    /**
     * Lead chưa phân cho đội (Sale Admin), cũ trước.
     */
    public function availableLeads(string $branchId, ?string $source = null, ?string $method = null): Builder
    {
        // Không có cơ hội đang xử lý (cùng điều kiện với Lead::scopeUnassigned): khách cũ được đưa về hàng chờ
        // còn các cơ hội đã kết thúc vẫn được phân
        return Lead::query()
            ->where('status', Lead::STATUS_NEW)
            ->where('branch_id', $branchId)
            ->whereDoesntHave('opportunities', fn ($q) => $q->where('status', Opportunity::STATUS_IN_PROGRESS))
            ->when($source, fn ($q) => $q->where('source_name', $source))
            ->when($method, fn ($q) => $q->where('contact_method', $method))
            ->orderBy('created_at')
            ->orderBy('lead_id');
    }

    /**
     * Lead đội đang chờ phân cho Salesperson (Cơ hội trưởng nhóm giữ ở stage đầu, chưa chăm sóc), cũ trước.
     */
    public function pendingOpportunities(string $leaderId, ?string $source = null, ?string $method = null): Builder
    {
        return Opportunity::query()
            ->where('employee_id', $leaderId)
            ->where('stage_id', Stage::UNTOUCHED)
            ->where('status', 'InProgress')
            ->whereDoesntHave('careResults')
            ->whereHas('lead', fn ($q) => $q
                ->when($source, fn ($q) => $q->where('source_name', $source))
                ->when($method, fn ($q) => $q->where('contact_method', $method)))
            ->orderBy('conversion_date')
            ->orderBy('opportunity_id');
    }

    /* ── Khách cũ quay lại ────────────────────────────────────────────── */

    /**
     * Người / đội từng phụ trách khách cũ, theo cơ hội đã kết thúc gần nhất của Lead:
     * [lead_id => ['employee_id' => người phụ trách, 'leader_id' => trưởng nhóm của đội người đó đang ở]].
     * Lead chưa có cơ hội nào đã kết thúc (khách mới) không có trong kết quả.
     *
     * @param  string[]  $leadIds
     */
    public function previousOwners(array $leadIds): array
    {
        if (! $leadIds) {
            return [];
        }

        return Opportunity::query()
            ->whereIn('lead_id', $leadIds)
            ->where('status', '!=', Opportunity::STATUS_IN_PROGRESS)
            ->with(['employee:employee_id,team_id', 'employee.team:team_id,team_leader_id', 'employee.ledTeam:team_id,team_leader_id'])
            ->orderByDesc('conversion_date')
            ->orderByDesc('opportunity_id')
            ->get(['opportunity_id', 'lead_id', 'employee_id', 'conversion_date'])
            ->unique('lead_id')
            ->mapWithKeys(fn (Opportunity $o) => [$o->lead_id => [
                'employee_id' => $o->employee_id,
                'leader_id'   => $o->employee?->ledTeam?->team_leader_id ?? $o->employee?->team?->team_leader_id,
            ]])
            ->all();
    }

    /**
     * Ghép Lead với người nhận theo kế hoạch; số lượng của từng người giữ đúng kế hoạch:
     *   1. Lead có người nhận ưu tiên (khách cũ, người thân đã có người phụ trách) → người đó, nếu còn suất;
     *   2. Lead có người thân trong cùng lần phân → cùng người nhận với người thân, nếu còn suất;
     *   3. còn lại lần lượt theo kế hoạch.
     *
     * @param  array<string, ?string>  $preferred  [mã (Lead / cơ hội) => người nhận ưu tiên | null], theo thứ tự phân (cũ trước)
     * @param  array<string, int>  $plan  [người nhận => số lượng]
     * @param  array<string, string>  $families  [mã => mã nhóm người thân] (familiesIn)
     * @return array<string, string[]>  [người nhận => [mã...]]
     */
    public function matchPreferred(array $preferred, array $plan, array $families = []): array
    {
        $remaining = $plan;
        $groups = array_fill_keys(array_keys($plan), []);
        $familyOwner = [];
        $rest = [];

        $take = function (string $key, string $owner) use (&$groups, &$remaining, &$familyOwner, $families) {
            $groups[$owner][] = $key;
            $remaining[$owner]--;
            if (isset($families[$key])) {
                $familyOwner[$families[$key]] ??= $owner;
            }
        };

        // 1. Có người nhận ưu tiên (khách cũ / người thân đã có người phụ trách) và người đó còn suất
        foreach ($preferred as $key => $owner) {
            if ($owner !== null && ($remaining[$owner] ?? 0) > 0) {
                $take((string) $key, $owner);
            } else {
                $rest[] = (string) $key;
            }
        }

        // 2. Còn lại: theo người thân cùng lần phân nếu người đó còn suất, không thì lần lượt theo kế hoạch
        foreach ($rest as $key) {
            $owner = $familyOwner[$families[$key] ?? ''] ?? null;

            if ($owner === null || $remaining[$owner] <= 0) {
                $owner = array_key_first(array_filter($remaining, fn ($n) => $n > 0));
            }

            $take($key, $owner);
        }

        return $groups;
    }

    /* ── Người thân dùng chung liên hệ ────────────────────────────────── */

    /**
     * Lead khác dùng chung SĐT / email với từng Lead (người thân: anh chị em, phụ huynh đăng ký cho các con…),
     * tìm cả trong Leads và Students: [lead_id => [lead_id người thân...]]; Lead không có người thân không có trong kết quả.
     *
     * @param  string[]  $leadIds
     * @return array<string, string[]>
     */
    public function relatedLeads(array $leadIds): array
    {
        $leads = Lead::query()->whereIn('lead_id', $leadIds)->get(['lead_id', 'phone', 'email']);
        $phones = $leads->pluck('phone')->filter()->unique()->values()->all();
        $emails = $leads->pluck('email')->filter()->map(fn ($e) => Str::lower($e))->unique()->values()->all();

        if (! $phones && ! $emails) {
            return [];
        }

        // Liên hệ → các Lead mang liên hệ đó (email so không phân biệt hoa thường)
        $byContact = [];
        $add = function (?string $type, ?string $value, string $leadId) use (&$byContact) {
            if ($value) {
                $byContact[$type.':'.Str::lower($value)][$leadId] = true;
            }
        };

        $contactFilter = fn ($q) => $q->whereIn('phone', $phones)->orWhereIn('email', $emails);

        Lead::query()->where($contactFilter)->get(['lead_id', 'phone', 'email'])
            ->each(function (Lead $lead) use ($add) {
                $add('p', $lead->phone, $lead->lead_id);
                $add('e', $lead->email, $lead->lead_id);
            });

        Student::query()->where($contactFilter)->with('opportunity:opportunity_id,lead_id')->get(['student_id', 'phone', 'email', 'opportunity_id'])
            ->each(function (Student $student) use ($add) {
                if ($leadId = $student->opportunity?->lead_id) {
                    $add('p', $student->phone, $leadId);
                    $add('e', $student->email, $leadId);
                }
            });

        $result = [];
        foreach ($leads as $lead) {
            $related = array_keys(($byContact['p:'.$lead->phone] ?? []) + ($byContact['e:'.Str::lower((string) $lead->email)] ?? []));
            $related = array_values(array_diff($related, [$lead->lead_id]));
            if ($related) {
                $result[$lead->lead_id] = $related;
            }
        }

        return $result;
    }

    /**
     * Nhóm người thân trong cùng lần phân: [lead_id => mã nhóm] cho các Lead có người thân cũng nằm trong $leadIds.
     *
     * @param  string[]  $leadIds
     * @return array<string, string>
     */
    public function familiesIn(array $leadIds, ?array $related = null): array
    {
        $related ??= $this->relatedLeads($leadIds);
        $families = [];

        foreach ($related as $leadId => $others) {
            $inBatch = array_intersect($others, $leadIds);
            if ($inBatch) {
                $families[$leadId] = min([$leadId, ...$inBatch]);
            }
        }

        return $families;
    }

    /**
     * Đội / người nên nhận từng Lead:
     *   - 'returning': khách cũ → người phụ trách cơ hội đã kết thúc gần nhất của chính Lead (previousOwners);
     *   - 'family': người thân dùng chung liên hệ đã có cơ hội → người phụ trách cơ hội gần nhất của người thân.
     * [lead_id => ['employee_id', 'leader_id', 'reason' => 'returning'|'family', 'related' => lead_id người thân | null]].
     *
     * @param  string[]  $leadIds
     */
    public function suggestedOwners(array $leadIds, ?array $related = null): array
    {
        $suggested = array_map(fn ($owner) => $owner + ['reason' => 'returning', 'related' => null], $this->previousOwners($leadIds));

        $related ??= $this->relatedLeads($leadIds);
        $relatedIds = array_unique(array_merge([], ...array_values($related)));

        if (! $relatedIds) {
            return $suggested;
        }

        // Cơ hội gần nhất của từng người thân (mọi trạng thái)
        $latest = Opportunity::query()
            ->whereIn('lead_id', $relatedIds)
            ->with(['employee:employee_id,team_id', 'employee.team:team_id,team_leader_id', 'employee.ledTeam:team_id,team_leader_id'])
            ->orderByDesc('conversion_date')
            ->orderByDesc('opportunity_id')
            ->get(['opportunity_id', 'lead_id', 'employee_id', 'conversion_date'])
            ->unique('lead_id')
            ->keyBy('lead_id');

        foreach ($related as $leadId => $others) {
            if (isset($suggested[$leadId])) {
                continue;
            }

            $opportunity = collect($others)->map(fn ($id) => $latest[$id] ?? null)->filter()
                ->sortByDesc(fn (Opportunity $o) => $o->conversion_date.'|'.$o->opportunity_id)
                ->first();

            if ($opportunity) {
                $suggested[$leadId] = [
                    'employee_id' => $opportunity->employee_id,
                    'leader_id'   => $opportunity->employee?->ledTeam?->team_leader_id ?? $opportunity->employee?->team?->team_leader_id,
                    'reason'      => 'family',
                    'related'     => $opportunity->lead_id,
                ];
            }
        }

        return $suggested;
    }

    /* ── Đối tượng nhận Lead ──────────────────────────────────────────── */

    /**
     * Đội nhận Lead của chi nhánh: đội có trưởng nhóm đang làm việc thuộc chi nhánh (Lead của đội do trưởng nhóm giữ).
     */
    public function branchTeams(string $branchId): Collection
    {
        return SalesTeam::query()
            ->whereHas('leader', fn ($q) => $q->where('branch_id', $branchId)->where('status', Employee::STATUS_WORKING))
            ->with('leader:employee_id,full_name')
            ->orderBy('team_id')
            ->get();
    }

    /**
     * Salesperson đang làm việc của đội.
     */
    public function teamSalespersons(string $teamId): Collection
    {
        return Employee::query()
            ->where('team_id', $teamId)
            ->where('status', Employee::STATUS_WORKING)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'job_title']);
    }

    /* ── Phân một Lead cụ thể ─────────────────────────────────────────── */

    /**
     * Sale Admin phân một Lead cụ thể cho đội (trưởng nhóm $leaderId): như assignToTeams() nhưng chỉ định Lead.
     *
     * @throws RuntimeException  Lead không còn chờ phân / đội không hợp lệ
     */
    public function assignLeadToTeam(string $leadId, string $leaderId): void
    {
        DB::transaction(function () use ($leadId, $leaderId) {
            $lead = Lead::query()->whereKey($leadId)->unassigned()->lockForUpdate()->first();

            if (! $lead) {
                throw new RuntimeException(__('leads.assign_one.unavailable'));
            }
            if (! $this->branchTeams($lead->branch_id)->contains('team_leader_id', $leaderId)) {
                throw new RuntimeException(__('leads.assign_one.invalid_team'));
            }

            Opportunity::insert([
                'opportunity_id'  => Opportunity::nextIds(1)[0],
                'conversion_date' => Carbon::now(),
                'status'          => Opportunity::STATUS_IN_PROGRESS,
                'lead_id'         => $lead->lead_id,
                'stage_id'        => Stage::UNTOUCHED,
                'employee_id'     => $leaderId,
            ]);
        });

        // insert() không phát sự kiện model
        Lead::touchStats();
    }

    /**
     * Sale Leader phân một Lead cụ thể đội đang giữ cho Salesperson $employeeId: như assignToSalespersons()
     * nhưng chỉ định Lead. Ngưỡng MAX_PER_SALESPERSON chỉ là cảnh báo (được phép vượt).
     *
     * @throws RuntimeException  Lead không còn chờ phân / Salesperson không thuộc đội
     */
    public function assignLeadToSalesperson(string $leaderId, string $leadId, string $employeeId): void
    {
        DB::transaction(function () use ($leaderId, $leadId, $employeeId) {
            $opportunity = $this->pendingOpportunities($leaderId)->where('lead_id', $leadId)->lockForUpdate()->first();

            if (! $opportunity) {
                throw new RuntimeException(__('leads.assign_one.unavailable'));
            }

            $team = SalesTeam::query()->where('team_leader_id', $leaderId)->first();
            if (! $team || ! $this->teamSalespersons($team->team_id)->contains('employee_id', $employeeId)) {
                throw new RuntimeException(__('leads.assign_one.invalid_person'));
            }

            $opportunity->update(['employee_id' => $employeeId, 'conversion_date' => Carbon::now()]);
            Lead::whereKey($leadId)->update(['status' => Lead::STATUS_CONVERTED]);
        });
    }

    /* ── Ghi kết quả ──────────────────────────────────────────────────── */

    /**
     * Phân Lead cho các đội: $plan = [trưởng nhóm employee_id => số Lead]. Trả về số Lead đã phân.
     */
    public function assignToTeams(array $plan, string $branchId, ?string $source, ?string $method): int
    {
        $plan = array_filter($plan);
        $total = array_sum($plan);

        return DB::transaction(function () use ($plan, $total, $branchId, $source, $method) {
            $leadIds = $this->availableLeads($branchId, $source, $method)
                ->lockForUpdate()
                ->limit($total)
                ->pluck('lead_id')
                ->all();

            if (count($leadIds) < $total) {
                throw new RuntimeException(__('leads.distribute.exceed'));
            }

            // Khách cũ / người thân ưu tiên về đội đang / từng phụ trách; người thân cùng lần phân về chung một đội
            $related = $this->relatedLeads($leadIds);
            $owners = $this->suggestedOwners($leadIds, $related);
            $groups = $this->matchPreferred(
                array_combine($leadIds, array_map(fn ($id) => $owners[$id]['leader_id'] ?? null, $leadIds)),
                $plan,
                $this->familiesIn($leadIds, $related),
            );

            $ids = Opportunity::nextIds($total);
            $now = Carbon::now();
            $rows = [];
            $i = 0;
            foreach ($groups as $leaderId => $groupLeadIds) {
                foreach ($groupLeadIds as $leadId) {
                    $rows[] = [
                        'opportunity_id'  => $ids[$i++],
                        'conversion_date' => $now,
                        'status'          => 'InProgress',
                        'lead_id'         => $leadId,
                        'stage_id'        => Stage::UNTOUCHED,
                        'employee_id'     => $leaderId,
                    ];
                }
            }

            Opportunity::insert($rows);
            Lead::touchStats(); // insert() không phát sự kiện model

            return $total;
        });
    }

    /**
     * Phân Lead của đội cho salesperson: $plan = [employee_id => số Lead]. Trả về số Lead đã phân.
     */
    public function assignToSalespersons(string $leaderId, array $plan, ?string $source, ?string $method): int
    {
        $plan = array_filter($plan);
        $total = array_sum($plan);

        return DB::transaction(function () use ($leaderId, $plan, $total, $source, $method) {
            // [opportunity_id => lead_id], theo thứ tự phân (cũ trước)
            $oppLeads = $this->pendingOpportunities($leaderId, $source, $method)
                ->lockForUpdate()
                ->limit($total)
                ->pluck('lead_id', 'opportunity_id')
                ->all();

            if (count($oppLeads) < $total) {
                throw new RuntimeException(__('leads.distribute.exceed'));
            }

            // Khách cũ / người thân ưu tiên về Salesperson đang / từng phụ trách; người thân cùng lần phân về chung một người
            $leadIds = array_values($oppLeads);
            $related = $this->relatedLeads($leadIds);
            $owners = $this->suggestedOwners($leadIds, $related);
            $families = $this->familiesIn($leadIds, $related);
            $groups = $this->matchPreferred(
                array_map(fn ($leadId) => $owners[$leadId]['employee_id'] ?? null, $oppLeads),
                $plan,
                array_filter(array_map(fn ($leadId) => $families[$leadId] ?? null, $oppLeads)),
            );

            $now = Carbon::now();
            foreach ($groups as $employeeId => $oppIds) {
                if ($oppIds) {
                    Opportunity::whereIn('opportunity_id', $oppIds)
                        ->update(['employee_id' => $employeeId, 'conversion_date' => $now]);
                }
            }

            Lead::whereIn('lead_id', array_values($oppLeads))
                ->update(['status' => Lead::STATUS_CONVERTED]);
            Lead::touchStats(); // update() hàng loạt không phát sự kiện model

            return $total;
        });
    }
}
