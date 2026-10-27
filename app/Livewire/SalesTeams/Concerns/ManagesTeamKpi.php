<?php

namespace App\Livewire\SalesTeams\Concerns;

use App\Models\SalesTarget;
use App\Models\TeamSalesTarget;
use App\Services\SalesPerformanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Popup KPI đội (danh sách đội kinh doanh, cột Thao tác - chỉ Sale Admin): mỗi tháng đặt chỉ tiêu cho đội
 * theo các loại chỉ tiêu trong SalesTargets (doanh thu, học viên mới) → TeamSalesTargets.
 *
 * Chỉ đặt cho tháng hiện tại và KPI_NEXT_MONTHS tháng tới. KPI của một tháng chỉ đặt một lần:
 * đã đặt (có chỉ tiêu chưa huỷ) thì khoá, popup chỉ xem. Chỉ tiêu bỏ trống thì không tạo dòng.
 * Chưa có KPI thì gợi ý sẵn theo KPI tháng trước của đội.
 *
 * Component dùng trait có sẵn: $modal, $teamId, $form, $notice, openModal(), closeModal(), authorizeManage(), editingTeam.
 */
trait ManagesTeamKpi
{
    // Số tháng tới (ngoài tháng hiện tại) đặt KPI trước được
    const KPI_NEXT_MONTHS = 2;

    // Giới hạn DECIMAL(18,2)
    const KPI_MAX_VALUE = 9999999999999999;

    /**
     * Các loại chỉ tiêu (ô nhập trong popup): chỉ những loại báo cáo so sánh được (SalesPerformanceReport::TARGETS).
     */
    #[Computed]
    public function kpiTypes(): Collection
    {
        return SalesTarget::whereIn('target_type', array_keys(SalesPerformanceReport::TARGETS))->orderBy('target_id')->get();
    }

    /**
     * Tháng đặt KPI được: tháng hiện tại + KPI_NEXT_MONTHS tháng tới ('Y-m').
     */
    #[Computed]
    public function kpiMonths(): array
    {
        $current = CarbonImmutable::today()->startOfMonth();

        return array_map(fn ($i) => $current->addMonths($i)->format('Y-m'), range(0, self::KPI_NEXT_MONTHS));
    }

    public function kpiMonthLabel(string $month): string
    {
        return CarbonImmutable::parse($month.'-01')->format('m/Y');
    }

    /**
     * KPI của đội đang mở ở tháng đang chọn (kể cả đã huỷ): [target_id => TeamSalesTarget].
     */
    #[Computed]
    public function kpiCurrent(): array
    {
        return $this->kpiRows($this->kpiMonth());
    }

    /**
     * KPI còn hiệu lực tháng trước tháng đang chọn (gợi ý khi nhập): [target_id => TeamSalesTarget].
     */
    #[Computed]
    public function kpiPrevious(): array
    {
        $previous = CarbonImmutable::parse($this->kpiMonth().'-01')->subMonth()->format('Y-m');

        return $this->activeOnly($this->kpiRows($previous));
    }

    /**
     * Tháng đang chọn đã có KPI (chưa huỷ) chưa.
     */
    public function kpiIsSet(): bool
    {
        return (bool) $this->activeOnly($this->kpiCurrent);
    }

    /**
     * Tên loại chỉ tiêu theo ngôn ngữ (theo target_type), loại lạ thì dùng target_name trong CSDL.
     */
    public function kpiTypeLabel(SalesTarget $type): string
    {
        $key = 'employees.teams.kpi.types.'.$type->target_type;

        return __($key) !== $key ? __($key) : $type->target_name;
    }

    /**
     * Loại chỉ tiêu tính bằng tiền (nhập số tiền, còn lại nhập số nguyên).
     */
    public function kpiIsMoney(SalesTarget $type): bool
    {
        return $type->target_type === 'Revenue';
    }

    public function kpiFormat(SalesTarget $type, float $value): string
    {
        return number_format($value, 0, ',', '.').($this->kpiIsMoney($type) ? ' ₫' : '');
    }

    /**
     * Mở popup KPI của một đội (mặc định tháng hiện tại).
     */
    public function openKpi(string $teamId): void
    {
        $this->authorizeManage();

        $this->teamId = $teamId;
        abort_unless($this->editingTeam, 404);

        $this->form = ['month' => $this->kpiMonths[0], 'values' => []];
        $this->openModal('kpi');
        $this->loadKpiValues();
    }

    /**
     * Đổi tháng trong popup → nạp lại KPI của tháng đó.
     */
    public function updatedFormMonth(): void
    {
        if ($this->modal === 'kpi') {
            $this->loadKpiValues();
            $this->resetValidation();
        }
    }

    /**
     * Lưu KPI của đội cho tháng đang chọn.
     */
    protected function saveKpi(): void
    {
        $team = $this->editingTeam;
        abort_unless($team, 404);

        $data = $this->validateKpi();
        $month = $data['month'].'-01';

        $saved = DB::transaction(function () use ($data, $team, $month) {
            // Kiểm tra lại lúc ghi (khoá dòng): tháng đã có KPI thì không ghi đè
            $locked = TeamSalesTarget::query()
                ->where('team_id', $team->team_id)
                ->where('target_month', $month)
                ->where('status', '<>', TeamSalesTarget::STATUS_CANCELLED)
                ->lockForUpdate()
                ->exists();

            if ($locked) {
                return false;
            }

            $rows = [];
            foreach ($this->kpiTypes as $type) {
                $value = $data['values'][$type->target_id] ?? null;

                if ($value !== null && $value !== '') {
                    $rows[] = [
                        'target_id'    => $type->target_id,
                        'team_id'      => $team->team_id,
                        'target_month' => $month,
                        'target_value' => $value,
                        'status'       => TeamSalesTarget::STATUS_ACTIVE,
                    ];
                }
            }

            // upsert: dòng đã huỷ từ trước (nếu có) được bật lại theo giá trị mới
            TeamSalesTarget::upsert($rows, ['target_id', 'team_id', 'target_month'], ['target_value', 'status']);

            return true;
        });

        if (! $saved) {
            $this->loadKpiValues();
            $this->addError('values', __('employees.teams.kpi.locked'));

            return;
        }

        $this->notice = [
            'type' => 'success',
            'text' => __('employees.teams.kpi.saved', ['team' => $team->team_name, 'month' => $this->kpiMonthLabel($data['month'])]),
        ];
        $this->closeModal();
    }

    /* ── Nội bộ ───────────────────────────────────────────────────────── */

    private function kpiMonth(): string
    {
        $month = (string) ($this->form['month'] ?? '');

        return in_array($month, $this->kpiMonths, true) ? $month : $this->kpiMonths[0];
    }

    /**
     * KPI của đội đang mở trong một tháng 'Y-m': [target_id => TeamSalesTarget].
     */
    private function kpiRows(string $month): array
    {
        if (! $this->teamId) {
            return [];
        }

        return TeamSalesTarget::query()
            ->where('team_id', $this->teamId)
            ->where('target_month', $month.'-01')
            ->get()
            ->keyBy('target_id')
            ->all();
    }

    private function activeOnly(array $rows): array
    {
        return array_filter($rows, fn (TeamSalesTarget $t) => $t->status !== TeamSalesTarget::STATUS_CANCELLED);
    }

    /**
     * Điền ô nhập: KPI đang có của tháng, chưa có thì theo KPI tháng trước.
     */
    private function loadKpiValues(): void
    {
        unset($this->kpiCurrent, $this->kpiPrevious);

        $rows = $this->activeOnly($this->kpiCurrent) ?: $this->kpiPrevious;

        // Số dạng chuỗi cho ô nhập, bỏ phần thập phân .00
        $this->form['values'] = array_map(
            fn (TeamSalesTarget $t) => rtrim(rtrim(number_format((float) $t->target_value, 2, '.', ''), '0'), '.'),
            $rows
        );
    }

    /**
     * Kiểm tra dữ liệu popup (luôn kiểm tra lại phía server): tháng chưa có KPI, nhập ít nhất một chỉ tiêu.
     */
    private function validateKpi(): array
    {
        $data = [
            'month'  => (string) ($this->form['month'] ?? ''),
            'values' => array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) ($this->form['values'] ?? [])),
        ];

        $rules = [
            'month'  => ['required', Rule::in($this->kpiMonths)],
            'values' => ['array'],
        ];
        $attributes = ['month' => __('employees.teams.kpi.month')];

        foreach ($this->kpiTypes as $type) {
            $key = 'values.'.$type->target_id;
            $rules[$key] = ['nullable', $this->kpiIsMoney($type) ? 'numeric' : 'integer', 'min:0', 'max:'.self::KPI_MAX_VALUE];
            $attributes[$key] = $this->kpiTypeLabel($type);
        }

        return Validator::make($data, $rules, [
            'month.in' => __('employees.teams.kpi.month_invalid'),
        ], $attributes)->after(function ($validator) use ($data) {
            if ($this->kpiIsSet()) {
                $validator->errors()->add('values', __('employees.teams.kpi.locked'));

                return;
            }

            $filled = array_filter($data['values'], fn ($v) => $v !== null && $v !== '');
            if (! $filled) {
                $validator->errors()->add('values', __('employees.teams.kpi.empty'));
            }
        })->validate();
    }
}
