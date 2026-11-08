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

/**
 * Kiểm tra trùng khi Sale Admin thêm / import Lead (khách cũ quay lại đăng ký khoá mới).
 *
 * Cùng một người học = khớp liên hệ + khớp họ tên:
 *   - Liên hệ: SĐT, email (tìm trong Leads và Students) hoặc link Facebook trang cá nhân (Leads.source_url).
 *   - Họ tên: bỏ dấu, không phân biệt hoa thường, gộp khoảng trắng; so với tên Lead và tên học viên của các
 *     cơ hội thuộc Lead (Lead có thể mang tên phụ huynh, học viên mang tên người học).
 * Không khớp liên hệ thì coi là khách mới, không xét tên (trùng tên là chuyện bình thường).
 *
 *   NEW          không khớp liên hệ                              → tạo Lead mới
 *   OTHER_PERSON khớp liên hệ, khác họ tên (VD con thứ hai)       → hỏi "người học khác?"; import: tạo mới kèm ghi chú
 *   IN_QUEUE     cùng người, Lead đang 'New' (chờ phân)            → không tạo Lead mới
 *   IN_PROGRESS  cùng người, còn cơ hội InProgress                 → không tạo Lead mới, báo Salesperson đang tư vấn
 *   REOPEN       cùng người, không còn cơ hội InProgress           → đưa Lead cũ về 'New' để phân lại
 *   AMBIGUOUS    khớp liên hệ + họ tên với nhiều Lead              → Sale Admin chọn; import: báo lỗi
 *
 * Khi đưa Lead cũ về hàng chờ: chỉ bổ sung SĐT / email / link nguồn còn trống, không ghi đè thông tin cũ
 * (nguồn, chi nhánh, kênh liên hệ, họ tên gắn với các cơ hội / đơn hàng đã có); phần khác chỉ báo lại.
 */
class LeadDuplicateService
{
    const NEW = 'new';
    const OTHER_PERSON = 'other_person';
    const IN_QUEUE = 'in_queue';
    const IN_PROGRESS = 'in_progress';
    const REOPEN = 'reopen';
    const AMBIGUOUS = 'ambiguous';

    // Được bổ sung vào Lead cũ khi Lead cũ đang để trống
    const FILLABLE = ['phone', 'email', 'source_url'];

    // Thông tin liên hệ (cùng danh sách với EditsLead::LEAD_CONTACT_FIELDS)
    const CONTACT_FIELDS = ['full_name', 'phone', 'email', 'source_url'];

    // Đường dẫn Facebook không phải trang cá nhân (bài viết, nhóm, trang chức năng…)
    const FACEBOOK_RESERVED = [
        'profile.php', 'groups', 'pages', 'watch', 'events', 'share', 'sharer.php', 'photo.php', 'photo',
        'permalink.php', 'story.php', 'reel', 'reels', 'marketplace', 'login', 'home.php', 'hashtag', 'gaming',
    ];

    /**
     * Kết quả kiểm tra: ['case' => hằng số ở trên, 'lead' => Lead cùng người (IN_QUEUE / IN_PROGRESS / REOPEN),
     * 'leads' => các Lead liên quan (OTHER_PERSON: khớp liên hệ; AMBIGUOUS: khớp cả họ tên)].
     */
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

    /**
     * Tình trạng của Lead cùng người: IN_QUEUE / IN_PROGRESS / REOPEN.
     */
    public function stateOf(Lead $lead): string
    {
        if ($lead->status === Lead::STATUS_NEW) {
            return self::IN_QUEUE;
        }

        return $lead->opportunities->contains('status', Opportunity::STATUS_IN_PROGRESS) ? self::IN_PROGRESS : self::REOPEN;
    }

    /**
     * Lead kèm cơ hội, người phụ trách và học viên (để xét tình trạng / hiển thị).
     */
    public function find(string $leadId): ?Lead
    {
        return $this->withDetails(Lead::query()->whereKey($leadId))->first();
    }

    /**
     * Lead khớp liên hệ (SĐT / email / link Facebook cá nhân), cũ trước.
     */
    public function contactMatches(array $data): Collection
    {
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;
        $facebook = $this->facebookKey($data['source_url'] ?? null);

        $ids = collect();

        if ($phone || $email) {
            // Học viên giữ SĐT / email riêng (sửa được ở hồ sơ học viên) → tìm cả ở Students
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
            'opportunities.student:student_id,full_name,status,opportunity_id',
        ];
    }

    /**
     * Họ tên của Lead và của các học viên thuộc Lead (đã chuẩn hoá).
     */
    public function namesOf(Lead $lead): Collection
    {
        return collect([$lead->full_name])
            ->merge($lead->opportunities->map(fn (Opportunity $o) => $o->student?->full_name))
            ->filter()
            ->map(fn (string $name) => $this->nameKey($name))
            ->unique()
            ->values();
    }

    /**
     * "Nguyễn  Văn Ân" → "nguyen van an".
     */
    public function nameKey(string $name): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', Str::ascii($name))));
    }

    /**
     * Khoá so khớp link Facebook trang cá nhân: "id:<số>" (profile.php?id=) hoặc "u:<username>";
     * null nếu không phải link trang cá nhân (bài viết, nhóm, link khác…).
     */
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

    /* ── Đưa Lead cũ về hàng chờ ──────────────────────────────────────── */

    /**
     * Đưa Lead cũ về 'New' để phân lại (khách đã từng đăng ký, không còn cơ hội đang tư vấn).
     * Trả về ['lead' => Lead, 'filled' => [trường đã bổ sung], 'differences' => [trường => ['old', 'new']]].
     *
     * @throws RuntimeException  Lead không còn ở tình trạng đưa về hàng chờ được (VD vừa được phân / tạo cơ hội)
     */
    public function reopen(Lead $lead, array $data): array
    {
        return DB::transaction(function () use ($lead, $data) {
            // Khoá dòng Lead rồi mới đọc lại cơ hội: tránh trùng lúc Lead vừa được phân / vừa tạo cơ hội
            $locked = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail()->load($this->details());

            $state = $this->stateOf($locked);
            if ($state !== self::REOPEN) {
                throw new RuntimeException($this->message($state, $locked));
            }

            [$fill, $differences] = $this->compare($locked, $data);
            $locked->update($fill + ['status' => Lead::STATUS_NEW]);

            return ['lead' => $locked, 'filled' => array_keys($fill), 'differences' => $differences];
        });
    }

    /**
     * So dữ liệu mới nhập với Lead cũ: [trường bổ sung được (Lead cũ đang trống), trường khác Lead cũ].
     */
    public function compare(Lead $lead, array $data): array
    {
        $fill = [];
        $differences = [];

        foreach (LeadImportService::COLUMNS as $field) {
            $new = $data[$field] ?? null;
            $old = $lead->$field;

            if ($new === null || $new === '') {
                continue;
            }

            if (in_array($field, self::FILLABLE, true) && blank($old)) {
                $fill[$field] = $new;

                continue;
            }

            if (! $this->sameValue($field, $old, $new)) {
                $differences[$field] = ['old' => (string) $old, 'new' => $new];
            }
        }

        return [$fill, $differences];
    }

    /**
     * Giá trị cũ và mới coi là như nhau (họ tên bỏ dấu, email không phân biệt hoa thường, link Facebook đã chuẩn hoá).
     */
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

    /* ── Cập nhật Lead cũ từ bảng so sánh (cùng người) ───────────────── */

    /**
     * So sánh dữ liệu vừa nhập với Lead cũ: [trường => ['old', 'new', 'editable']] (dữ liệu mới có giá trị và khác
     * giá trị cũ). $updatable = false (chưa xác định cùng người) → chỉ để xem; true → 'editable' theo updatableFields().
     */
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

    /**
     * Trường được cập nhật từ bảng so sánh khi thêm Lead trùng (cùng người):
     *   - chưa phân cho đội (chưa có cơ hội nào): mọi trường, kể cả chi nhánh;
     *   - Salesperson đã tương tác (cơ hội đang mở có kết quả chăm sóc) hoặc không còn cơ hội đang mở: không cập nhật;
     *   - khách cũ (có cơ hội đã kết thúc): chỉ thông tin liên hệ - nguồn, kênh, chi nhánh gắn với đơn hàng cũ;
     *   - đội đang giữ / đã phân Salesperson nhưng chưa tương tác: mọi trường trừ chi nhánh (Lead đã thuộc đội
     *     của chi nhánh này).
     */
    public function updatableFields(Lead $lead): array
    {
        if (! $lead->opportunities()->exists()) {
            return LeadImportService::COLUMNS;
        }

        $active = $lead->opportunities()->where('status', Opportunity::STATUS_IN_PROGRESS)->latest('conversion_date')->first();

        // Đã tương tác, hoặc mọi cơ hội đã kết thúc mà Lead chưa về hàng chờ (dùng "Đăng ký khoá mới" thay vì cập nhật)
        if ($active ? $active->careResults()->exists() : $lead->status !== Lead::STATUS_NEW) {
            return [];
        }

        if ($lead->isReturning()) {
            return self::CONTACT_FIELDS;
        }

        return array_values(array_diff(LeadImportService::COLUMNS, ['branch_id']));
    }

    /**
     * Salesperson đã tương tác với khách (cơ hội đang mở đã có kết quả chăm sóc).
     */
    public function contacted(Lead $lead): bool
    {
        return $lead->opportunities()->where('status', Opportunity::STATUS_IN_PROGRESS)->whereHas('careResults')->exists();
    }

    /**
     * Trường của Lead được sửa ở nút Sửa trang chi tiết Lead:
     *   - chưa có cơ hội nào (chưa phân cho đội): mọi trường;
     *   - đội đang giữ, chưa phân Salesperson (Lead 'Mới', không phải khách cũ): mọi trường trừ chi nhánh -
     *     Lead đã thuộc đội của chi nhánh này;
     *   - đã phân Salesperson / khách cũ đưa về hàng chờ: chỉ thông tin liên hệ (nguồn, kênh, chi nhánh gắn với
     *     cơ hội / đơn hàng đã có).
     */
    public function editableFields(Lead $lead): array
    {
        if (! $lead->opportunities()->exists()) {
            return LeadImportService::COLUMNS;
        }

        if ($lead->status === Lead::STATUS_NEW && ! $lead->isReturning()) {
            return array_values(array_diff(LeadImportService::COLUMNS, ['branch_id']));
        }

        return self::CONTACT_FIELDS;
    }

    /**
     * Cập nhật các trường $fields của Lead cũ theo dữ liệu mới, giới hạn theo updatableFields().
     * Trả về ['lead' => Lead, 'fields' => [trường đã cập nhật]].
     *
     * @throws RuntimeException  Lead đã đổi tình trạng (VD vừa được đưa về hàng chờ / vừa kết thúc cơ hội)
     */
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

            // Kiểm tra bản ghi sau khi cập nhật theo đúng luật của Lead (VD kênh Facebook vẫn phải có link nguồn)
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

    /* ── Thông báo ────────────────────────────────────────────────────── */

    /**
     * Câu thông báo cho tình trạng $case của Lead $lead (hoặc danh sách $leads với OTHER_PERSON / AMBIGUOUS).
     */
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

    /**
     * Tên người học: tên học viên gần nhất (nếu có), không thì tên Lead.
     */
    public function learnerName(Lead $lead): string
    {
        return $lead->opportunities->map(fn (Opportunity $o) => $o->student?->full_name)->filter()->first() ?? $lead->full_name;
    }

    /**
     * Ghi chú sau khi đưa Lead cũ về hàng chờ: các trường đã bổ sung, các trường khác Lead cũ (giữ thông tin cũ).
     *
     * @return string[]
     */
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
