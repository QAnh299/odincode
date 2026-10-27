<?php

namespace App\Livewire\Leads\Concerns;

use App\Models\Lead;
use App\Services\LeadImportService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Popup chỉnh sửa thông tin Lead, dùng ở chi tiết Lead (Sale Admin) và chi tiết cơ hội (Salesperson phụ trách).
 * Kiểm tra như khi thêm Lead (LeadImportService); trường không được sửa giữ nguyên giá trị hiện tại.
 * Component dùng trait khai báo Lead được sửa, các trường được sửa và việc cần làm sau khi lưu.
 */
trait EditsLead
{
    // Thông tin liên hệ: Salesperson sửa từ trang cơ hội, Sale Admin sửa mọi lúc
    public const LEAD_CONTACT_FIELDS = ['full_name', 'phone', 'email', 'source_url'];

    public bool $editingLead = false;

    public array $leadForm = [];

    /**
     * Lead người dùng hiện tại được sửa, null nếu không được sửa.
     */
    abstract protected function leadToEdit(): ?Lead;

    /**
     * Các cột của Leads được sửa (trong LeadImportService::COLUMNS).
     */
    abstract public function leadEditableFields(): array;

    abstract protected function leadUpdated(Lead $lead): void;

    /**
     * Có nút "Sửa" (view gọi).
     */
    public function canEditLead(): bool
    {
        return $this->leadToEdit() !== null;
    }

    public function startEditLead(): void
    {
        $lead = $this->leadToEdit();
        abort_unless($lead, 403);

        $this->resetValidation();
        $this->leadForm = collect($this->leadEditableFields())
            ->mapWithKeys(fn (string $field) => [$field => (string) $lead->$field])
            ->all();
        $this->editingLead = true;
    }

    public function cancelEditLead(): void
    {
        $this->editingLead = false;
        $this->resetValidation();
    }

    public function saveLeadEdit(LeadImportService $service): void
    {
        $lead = $this->leadToEdit();
        abort_unless($lead, 403);

        $fields = $this->leadEditableFields();
        $data = $service->normalize(array_merge(
            $lead->only(LeadImportService::COLUMNS),
            Arr::only($this->leadForm, $fields),
        ));

        $validated = Validator::make($data, $service->rules(), $service->messages(), $service->attributes())
            ->validate();

        $lead->update(Arr::only($validated, $fields));

        $this->editingLead = false;
        $this->leadUpdated($lead);
    }
}
