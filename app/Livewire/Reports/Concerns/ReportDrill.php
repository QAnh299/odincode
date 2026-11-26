<?php

namespace App\Livewire\Reports\Concerns;

use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Renderless;

trait ReportDrill
{
    public static int $drillStep = 50;

    abstract protected function drillKinds(): array;

    abstract protected function drillList(string $kind, array $params): array;

    protected function drillUrl(string $kind, array $params): ?string
    {
        return null;
    }

    public function drillTarget(array $params): array
    {
        $kind = $this->drillText($params, 'kind') ?? '';
        $url = isset($this->drillKinds()[$kind]) ? $this->drillUrl($kind, $params) : null;

        return $url ? ['url' => $url] : $params;
    }

    public function drillTargets(array $spec): array
    {
        if (isset($spec['drills'])) {
            $spec['drills'] = array_map(
                fn ($row) => array_map(fn ($params) => is_array($params) ? $this->drillTarget($params) : $params, $row),
                $spec['drills']
            );
        }

        return $spec;
    }

    protected function drillCharts(array $charts): array
    {
        return array_map(fn ($spec) => is_array($spec) ? $this->drillTargets($spec) : $spec, $charts);
    }

    protected function listUrlFor(string $page, array $query): ?string
    {
        $name = Auth::user()->role_name.'.'.$page;

        return Route::has($name) ? route($name, array_filter($query, fn ($v) => $v !== null && $v !== '')) : null;
    }

    protected function drillMonthRange(string $month, ?string $from = null, ?string $to = null): array
    {
        $start = $month.'-01';
        $end = date('Y-m-t', strtotime($start));

        return [$from !== null && $from > $start ? $from : $start, $to !== null && $to < $end ? $to : $end];
    }

    #[Renderless]
    public function drillHtml(array $params, int $pages = 1): string
    {
        $drill = $this->drill($params, $pages);

        return $drill ? view('livewire.reports.partials.report-drill-list', ['drill' => $drill])->render() : '';
    }

    protected function drill(array $params, int $pages = 1): ?array
    {
        $kind = $this->drillText($params, 'kind') ?? '';
        $columns = $this->drillKinds()[$kind] ?? null;
        if ($columns === null) {
            return null;
        }

        [$rows, $scope, $title] = $this->drillList($kind, $params) + [2 => null];
        $sums = [];
        foreach ($columns as $column => $type) {
            if ($type === 'money') {
                $sums[$column] = array_sum(array_column($rows, $column));
            }
        }

        return [
            'title'   => $title ?? __('reports.drill.kind.'.$kind),
            'scope'   => implode(' · ', array_filter($scope)),
            'columns' => $columns,
            'rows'    => $this->linkDrillRows(array_slice($rows, 0, max(1, $pages) * static::$drillStep)),
            'total'   => count($rows),
            'sums'    => $sums,
        ];
    }

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
