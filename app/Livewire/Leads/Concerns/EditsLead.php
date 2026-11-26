<?php

namespace App\Livewire\Leads\Concerns;

use App\Models\Lead;
use App\Services\LeadImportService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

trait EditsLead
{
    public bool $editingLead = false;

    public array $leadForm = [];

    abstract protected function leadToEdit(): ?Lead;

    abstract public function leadEditableFields(): array;

    abstract protected function leadUpdated(Lead $lead): void;

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
