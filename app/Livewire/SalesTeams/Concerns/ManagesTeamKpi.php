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

trait ManagesTeamKpi
{
    public const KPI_NEXT_MONTHS = 2;

    public const KPI_MAX_VALUE = 9999999999999999;

    #[Computed]
    public function kpiTypes(): Collection
    {
        return SalesTarget::whereIn('target_type', array_keys(SalesPerformanceReport::TARGETS))->orderBy('target_id')->get();
    }

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

    #[Computed]
    public function kpiCurrent(): array
    {
        return $this->kpiRows($this->kpiMonth());
    }

    #[Computed]
    public function kpiPrevious(): array
    {
        $previous = CarbonImmutable::parse($this->kpiMonth().'-01')->subMonth()->format('Y-m');

        return $this->activeOnly($this->kpiRows($previous));
    }

    public function kpiIsSet(): bool
    {
        return (bool) $this->activeOnly($this->kpiCurrent);
    }

    public function kpiTypeLabel(SalesTarget $type): string
    {
        $key = 'employees.teams.kpi.types.'.$type->target_type;

        return __($key) !== $key ? __($key) : $type->target_name;
    }

    public function kpiIsMoney(SalesTarget $type): bool
    {
        return $type->target_type === 'Revenue';
    }

    public function kpiFormat(SalesTarget $type, float $value): string
    {
        return number_format($value, 0, ',', '.').($this->kpiIsMoney($type) ? ' ₫' : '');
    }

    public function openKpi(string $teamId): void
    {
        $this->authorizeManage();

        $this->teamId = $teamId;
        abort_unless($this->editingTeam, 404);

        $this->form = ['month' => $this->kpiMonths[0], 'values' => []];
        $this->openModal('kpi');
        $this->loadKpiValues();
    }

    public function updatedFormMonth(): void
    {
        if ($this->modal === 'kpi') {
            $this->loadKpiValues();
            $this->resetValidation();
        }
    }

    protected function saveKpi(): void
    {
        $team = $this->editingTeam;
        abort_unless($team, 404);

        $data = $this->validateKpi();
        $month = $data['month'].'-01';

        $saved = DB::transaction(function () use ($data, $team, $month) {
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

    private function kpiMonth(): string
    {
        $month = (string) ($this->form['month'] ?? '');

        return in_array($month, $this->kpiMonths, true) ? $month : $this->kpiMonths[0];
    }

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

    private function loadKpiValues(): void
    {
        unset($this->kpiCurrent, $this->kpiPrevious);

        $rows = $this->activeOnly($this->kpiCurrent) ?: $this->kpiPrevious;

        $this->form['values'] = array_map(
            fn (TeamSalesTarget $t) => rtrim(rtrim(number_format((float) $t->target_value, 2, '.', ''), '0'), '.'),
            $rows
        );
    }

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
