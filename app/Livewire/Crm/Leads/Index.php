<?php

namespace App\Livewire\Crm\Leads;

use App\Livewire\Core\UI\BaseListPage;
use App\Livewire\Crm\Concerns\ManagesCrmAttachments;
use App\Models\Crm\CrmModel;
use App\Models\Crm\Lead;
use App\Models\Crm\LeadSource;
use App\Repositories\Crm\CrmLeadRepository;
use App\Services\Crm\CrmLeadService;
use App\Services\Crm\LeadConversionService;

class Index extends BaseListPage
{
    use ManagesCrmAttachments;

    public string $search = '';

    public string $status = '';

    public string $source_id = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $company_name = '';

    public string $mobile = '';

    public string $email = '';

    public string $lead_status = 'new';

    public string $form_source_id = '';

    public string $estimated_value = '';

    public string $description = '';

    public bool $showConvertModal = false;

    public ?int $convertingId = null;

    public bool $create_opportunity = false;

    public bool $showDetailModal = false;

    public ?int $detailLeadId = null;

    protected array $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'source_id' => ['except' => ''],
    ];

    public function mount(): void
    {
        if (request()->boolean('create')) {
            $this->openCreate();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'source_id']);
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openLeadDetail(int $id): void
    {
        $this->detailLeadId = $id;
        $this->showDetailModal = true;
    }

    public function closeLeadDetail(): void
    {
        $this->showDetailModal = false;
        $this->detailLeadId = null;
    }

    public function openEdit(int $id): void
    {
        $this->closeLeadDetail();

        $lead = Lead::query()->findOrFail($id);
        $this->editingId = $lead->id;
        $this->title = $lead->title;
        $this->first_name = $lead->first_name ?? '';
        $this->last_name = $lead->last_name ?? '';
        $this->company_name = $lead->company_name ?? '';
        $this->mobile = $lead->mobile ?? '';
        $this->email = $lead->email ?? '';
        $this->lead_status = $lead->status;
        $this->form_source_id = $lead->source_id ? (string) $lead->source_id : '';
        $this->estimated_value = $lead->estimated_value ? (string) (float) $lead->estimated_value : '';
        $this->description = $lead->description ?? '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(CrmLeadService $leads): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'lead_status' => ['required', 'string'],
            'form_source_id' => ['nullable', 'exists:crm_lead_sources,id'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
        ], [], [
            'title' => 'عنوان',
            'lead_status' => 'وضعیت',
            'description' => 'توضیحات',
        ]);

        $data = [
            'title' => $this->title,
            'first_name' => $this->first_name ?: null,
            'last_name' => $this->last_name ?: null,
            'company_name' => $this->company_name ?: null,
            'mobile' => $this->mobile ?: null,
            'email' => $this->email ?: null,
            'status' => $this->lead_status,
            'source_id' => $this->form_source_id !== '' ? (int) $this->form_source_id : null,
            'estimated_value' => $this->estimated_value !== '' ? (float) $this->estimated_value : null,
            'description' => trim($this->description),
        ];

        $actor = auth()->user();

        if ($this->editingId) {
            $lead = Lead::query()->findOrFail($this->editingId);
            $leads->update($lead, $data, $actor);
            session()->flash('success', 'سرنخ به‌روزرسانی شد.');
        } else {
            $lead = $leads->create($data, $actor);
            session()->flash('success', 'سرنخ جدید ثبت شد. می‌توانید فایل درخواست را در صفحه جزئیات پیوست کنید.');

            $this->closeModal();

            $this->redirect(route('crm.leads.show', $lead->id), navigate: true);

            return;
        }

        $this->closeModal();
    }

    public function openConvert(int $id): void
    {
        $this->closeLeadDetail();

        $this->convertingId = $id;
        $this->create_opportunity = false;
        $this->showConvertModal = true;
    }

    public function closeConvertModal(): void
    {
        $this->showConvertModal = false;
        $this->convertingId = null;
    }

    public function deleteLead(int $id, CrmLeadService $leads, CrmLeadRepository $leadRepository): void
    {
        $user = auth()->user();

        if (! $user?->hasPermission('crm.leads.delete')) {
            session()->flash('error', 'مجوز حذف سرنخ را ندارید.');

            return;
        }

        $lead = $leadRepository->findForShow($id, $user);

        if (! $lead) {
            session()->flash('error', 'سرنخ یافت نشد یا به آن دسترسی ندارید.');

            return;
        }

        try {
            $title = $lead->title;
            $leads->delete($lead, $user);

            if ($this->detailLeadId === $id) {
                $this->closeLeadDetail();
            }

            session()->flash('success', 'سرنخ «'.$title.'» حذف شد.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function convert(LeadConversionService $conversion): void
    {
        if (! $this->convertingId) {
            return;
        }

        $lead = Lead::query()->findOrFail($this->convertingId);

        try {
            $conversion->convert($lead, auth()->user(), [
                'create_opportunity' => $this->create_opportunity,
            ]);
            session()->flash('success', 'سرنخ با موفقیت تبدیل شد.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->closeConvertModal();
    }

    public function render(CrmLeadRepository $leads)
    {
        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'status' => $this->status !== '' ? $this->status : null,
            'source_id' => $this->source_id !== '' ? (int) $this->source_id : null,
        ]);

        $items = $leads->paginate($filters, auth()->user(), $this->perPage);
        $sources = LeadSource::query()->where('is_active', true)->orderBy('sort_order')->get();
        $statusOptions = CrmModel::STATUSES_LEAD;

        $editingLeadAttachments = collect();

        if ($this->editingId) {
            $editingLeadAttachments = Lead::query()
                ->with(['attachments' => fn ($q) => $q->latest('id')])
                ->find($this->editingId)
                ?->attachments ?? collect();
        }

        $detailLead = null;

        if ($this->showDetailModal && $this->detailLeadId) {
            $detailLead = $leads->findForShow($this->detailLeadId, auth()->user());

            if (! $detailLead) {
                $this->closeLeadDetail();
            }
        }

        return view('livewire.crm.leads.index', compact('items', 'sources', 'statusOptions', 'editingLeadAttachments', 'detailLead'));
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->reset([
            'title', 'first_name', 'last_name', 'company_name', 'mobile', 'email',
            'lead_status', 'form_source_id', 'estimated_value', 'description',
        ]);
        $this->lead_status = 'new';
        $this->resetValidation();
    }
}
