<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class LeadDuplicateService
{
    public const NEW = 'new';
    public const OTHER_PERSON = 'other_person';
    public const IN_QUEUE = 'in_queue';
    public const IN_PROGRESS = 'in_progress';
    public const REOPEN = 'reopen';
    public const AMBIGUOUS = 'ambiguous';

    public const CONTACT_FIELDS = ['phone', 'email', 'source_url'];

    public const FACEBOOK_RESERVED = [
        'profile.php', 'groups', 'pages', 'watch', 'events', 'share', 'sharer.php', 'photo.php', 'photo',
        'permalink.php', 'story.php', 'reel', 'reels', 'marketplace', 'login', 'home.php', 'hashtag', 'gaming',
    ];

    public function check(array $data): array
    {
        $leads = $this->contactMatches($data);

        if ($leads->isEmpty()) {
            return ['case' => self::NEW, 'lead' => null, 'leads' => $leads];
        }

        $name = $this->nameKey((string) ($data['full_name'] ?? ''));
        $same = $leads->filter(fn (Lead $lead) => $this->namesOf($lead)->contains($name))->values();

        return match ($same->count()) {
            0       => ['case' => self::OTHER_PERSON, 'lead' => null, 'leads' => $leads],
            1       => ['case' => $this->stateOf($same->first()), 'lead' => $same->first(), 'leads' => $leads],
            default => ['case' => self::AMBIGUOUS, 'lead' => null, 'leads' => $same],
        };
    }

    public function stateOf(Lead $lead): string
    {
        if ($lead->status === Lead::STATUS_NEW) {
            return self::IN_QUEUE;
        }

        return $lead->opportunities->contains('status', Opportunity::STATUS_IN_PROGRESS) ? self::IN_PROGRESS : self::REOPEN;
    }

    public function find(string $leadId): ?Lead
    {
        return $this->withDetails(Lead::query()->whereKey($leadId))->first();
    }

    public function contactMatches(array $data): Collection
    {
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;
        $facebook = $this->facebookKey($data['source_url'] ?? null);

        $ids = collect();

        if ($phone || $email) {
            $viaStudent = fn (string $column, string $value) => Opportunity::query()
                ->select('lead_id')
                ->whereHas('student', fn ($q) => $q->where($column, $value));

            $ids = Lead::query()
                ->where(function ($q) use ($phone, $email, $viaStudent) {
                    if ($phone) {
                        $q->orWhere('phone', $phone)->orWhereIn('lead_id', $viaStudent('phone', $phone));
                    }
                    if ($email) {
                        $q->orWhere('email', $email)->orWhereIn('lead_id', $viaStudent('email', $email));
                    }
                })
                ->pluck('lead_id');
        }

        if ($facebook) {
            [$type, $value] = explode(':', $facebook, 2);
            $like = '%'.($type === 'id' ? 'id=' : '/').addcslashes($value, '%_\\').'%';

            $ids = $ids->merge(Lead::query()
                ->where('source_url', 'like', $like)
                ->get(['lead_id', 'source_url'])
                ->filter(fn (Lead $lead) => $this->facebookKey($lead->source_url) === $facebook)
                ->pluck('lead_id'));
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        return $this->withDetails(Lead::query()->whereIn('lead_id', $ids->unique()->all()))
            ->orderBy('created_at')
            ->orderBy('lead_id')
            ->get();
    }

    protected function withDetails(Builder $query): Builder
    {
        return $query->with($this->details());
    }

    protected function details(): array
    {
        return [
            'opportunities' => fn ($q) => $q->orderByDesc('conversion_date')->orderByDesc('opportunity_id'),
            'opportunities.employee:employee_id,full_name',
            'opportunities.student:student_id,opportunity_id,full_name,status',
        ];
    }

    public function namesOf(Lead $lead): Collection
    {
        return collect([$lead->full_name])
            ->merge($lead->opportunities->map(fn (Opportunity $o) => $o->student?->full_name))
            ->filter()
            ->map(fn (string $name) => $this->nameKey($name))
            ->unique()
            ->values();
    }

    public function nameKey(string $name): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', Str::ascii($name))));
    }

    public function facebookKey(?string $url): ?string
    {
        $url = Str::lower(trim((string) $url));
        $url = preg_replace('#^https?://#', '', $url);
        $url = preg_replace('#^(www|m|mbasic|web|touch)\.#', '', $url);

        if (! preg_match('#^(?:facebook\.com|fb\.com)/(.+)$#', $url, $m)) {
            return null;
        }

        if (preg_match('#^profile\.php\?(?:[^\#]*&)?id=(\d+)#', $m[1], $id)) {
            return 'id:'.$id[1];
        }

        $path = trim(preg_split('/[?#]/', $m[1])[0], '/');

        if ($path === '' || str_contains($path, '/') || in_array($path, self::FACEBOOK_RESERVED, true)) {
            return null;
        }

        return 'u:'.$path;
    }

    public function reopen(Lead $lead, array $data, array $fields = []): array
    {
        return DB::transaction(function () use ($lead, $data, $fields) {
            $locked = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail()->load($this->details());

            $state = $this->stateOf($locked);
            if ($state !== self::REOPEN) {
                throw new RuntimeException($this->message($state, $locked));
            }

            [$fill, $differences] = $this->compare($locked, $data, array_intersect($fields, self::CONTACT_FIELDS));
            $locked->update($fill + ['status' => Lead::STATUS_NEW]);

            return ['lead' => $locked, 'filled' => array_keys($fill), 'differences' => $differences];
        });
    }

    public function compare(Lead $lead, array $data, array $fields): array
    {
        $fill = [];
        $differences = [];

        foreach (LeadImportService::COLUMNS as $field) {
            $new = $data[$field] ?? null;
            $old = $lead->$field;

            if ($new === null || $new === '' || (! blank($old) && $this->sameValue($field, $old, $new))) {
                continue;
            }

            if (in_array($field, $fields, true)) {
                $fill[$field] = $new;
            } else {
                $differences[$field] = ['old' => (string) $old, 'new' => $new];
            }
        }

        return [$fill, $differences];
    }

    public function sameValue(string $field, ?string $old, string $new): bool
    {
        return match ($field) {
            'full_name'  => $this->nameKey((string) $old) === $this->nameKey($new),
            'email'      => Str::lower((string) $old) === Str::lower($new),
            'source_url' => trim((string) $old) === trim($new)
                || ($this->facebookKey($old) !== null && $this->facebookKey($old) === $this->facebookKey($new)),
            default      => (string) $old === (string) $new,
        };
    }

    public function changes(Lead $lead, array $data, bool $updatable = true): array
    {
        $editable = $updatable ? $this->updatableFields($lead) : [];
        $changes = [];

        foreach (LeadImportService::COLUMNS as $field) {
            $new = $data[$field] ?? null;

            if ($new === null || $new === '' || (! blank($lead->$field) && $this->sameValue($field, $lead->$field, $new))) {
                continue;
            }

            $changes[$field] = [
                'old'      => (string) $lead->$field,
                'new'      => $new,
                'editable' => in_array($field, $editable, true),
            ];
        }

        return $changes;
    }

    public function updatableFields(Lead $lead): array
    {
        if ($this->contacted($lead)) {
            return [];
        }

        $fields = $this->editableFields($lead);

        return $fields === [] && $this->stateOf($lead) === self::REOPEN ? self::CONTACT_FIELDS : $fields;
    }

    public function lockReason(Lead $lead): string
    {
        return match (true) {
            $this->contacted($lead)              => 'contacted',
            $lead->isReturning()                 => 'returning',
            $lead->status !== Lead::STATUS_NEW   => 'assigned',
            default                              => 'branch',
        };
    }

    public function contacted(Lead $lead): bool
    {
        return $lead->opportunities()->where('status', Opportunity::STATUS_IN_PROGRESS)->whereHas('careResults')->exists();
    }

    public function editableFields(Lead $lead): array
    {
        $statuses = $lead->opportunities()->pluck('status');

        if ($statuses->isEmpty()) {
            return LeadImportService::COLUMNS;
        }

        if ($statuses->contains(fn ($status) => $status !== Opportunity::STATUS_IN_PROGRESS)) {
            $reopened = $lead->status === Lead::STATUS_NEW || $statuses->contains(Opportunity::STATUS_IN_PROGRESS);

            return $reopened ? self::CONTACT_FIELDS : [];
        }

        if ($lead->status === Lead::STATUS_NEW) {
            return array_values(array_diff(LeadImportService::COLUMNS, ['branch_id']));
        }

        return ['full_name', ...self::CONTACT_FIELDS];
    }

    public function updateExisting(string $leadId, array $data, array $fields, LeadImportService $service): array
    {
        return DB::transaction(function () use ($leadId, $data, $fields, $service) {
            $lead = Lead::query()->whereKey($leadId)->lockForUpdate()->firstOrFail()->load($this->details());

            if (! in_array($this->stateOf($lead), [self::IN_QUEUE, self::IN_PROGRESS], true)) {
                throw new RuntimeException(__('leads.duplicate.update_stale'));
            }

            $allowed = array_keys(array_filter($this->changes($lead, $data), fn ($change) => $change['editable']));
            $fields = array_values(array_intersect($fields, $allowed));

            if (! $fields) {
                return ['lead' => $lead, 'fields' => []];
            }

            $merged = $service->normalize(array_merge(
                $lead->only(LeadImportService::COLUMNS),
                array_intersect_key($data, array_flip($fields)),
            ));
            $validator = Validator::make($merged, $service->rules(), $service->messages(), $service->attributes());

            if ($validator->fails()) {
                throw new RuntimeException($validator->errors()->first());
            }

            $lead->update(array_intersect_key($merged, array_flip($fields)));

            return ['lead' => $lead, 'fields' => $fields];
        });
    }

    public function message(string $case, ?Lead $lead = null, ?Collection $leads = null): string
    {
        $list = fn () => ($leads ?? collect())->map(fn (Lead $l) => $l->lead_id.' - '.$l->full_name)->implode('; ');
        $active = $lead?->opportunities->firstWhere('status', Opportunity::STATUS_IN_PROGRESS);

        return match ($case) {
            self::IN_QUEUE     => __('leads.duplicate.in_queue', ['id' => $lead->lead_id, 'name' => $lead->full_name]),
            self::IN_PROGRESS  => __('leads.duplicate.in_progress', [
                'id'    => $lead->lead_id,
                'name'  => $this->learnerName($lead),
                'owner' => $active?->employee?->full_name ?? '-',
                'opp'   => $active?->opportunity_id ?? '-',
            ]),
            self::REOPEN       => __('leads.duplicate.reopen', ['id' => $lead->lead_id, 'name' => $this->learnerName($lead)]),
            self::OTHER_PERSON => __('leads.duplicate.other_person', ['leads' => $list()]),
            self::AMBIGUOUS    => __('leads.duplicate.ambiguous', ['leads' => $list()]),
            default            => '',
        };
    }

    public function learnerName(Lead $lead): string
    {
        return $lead->opportunities->map(fn (Opportunity $o) => $o->student?->full_name)->filter()->first() ?? $lead->full_name;
    }

    public function reopenNotes(array $result): array
    {
        $label = fn (string $field) => __('leads.field.'.$field);
        $notes = [];

        if ($result['filled']) {
            $notes[] = __('leads.duplicate.filled', ['fields' => implode(', ', array_map($label, $result['filled']))]);
        }

        if ($result['differences']) {
            $notes[] = __('leads.duplicate.differences', ['fields' => collect($result['differences'])
                ->map(fn ($diff, $field) => $label($field).': '.$diff['old'].' → '.$diff['new'])
                ->implode('; ')]);
        }

        return $notes;
    }
}
