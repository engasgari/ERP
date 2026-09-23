<?php

namespace App\Livewire\Crm\Concerns;

use App\Models\Crm\Contact;
use App\Models\Crm\LeadSource;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\Party;
use App\Models\User;
use App\Repositories\Crm\CrmOpportunityRepository;
use App\Services\Crm\CrmContactService;
use App\Services\Crm\CrmOpportunityService;
use App\Services\Crm\PartyCreationService;
use Illuminate\Validation\ValidationException;

trait ManagesCrmOpportunities
{
    public bool $showOpportunityModal = false;

    public ?int $editingOpportunityId = null;

    public string $form_title = '';

    public string $form_party_id = '';

    public string $form_contact_id = '';

    public string $form_pipeline_id = '';

    public string $form_stage_id = '';

    public string $form_assigned_user_id = '';

    public string $form_amount = '';

    public string $form_probability = '';

    public string $form_expected_close_date = '';

    public string $form_source_id = '';

    public string $form_description = '';

    public bool $showQuickCustomerModal = false;

    public string $quick_customer_name = '';

    public string $quick_customer_kind = 'company';

    public string $quick_customer_mobile = '';

    public string $quick_customer_phone = '';

    public string $quick_customer_email = '';

    public bool $showQuickContactModal = false;

    public string $quick_contact_first_name = '';

    public string $quick_contact_last_name = '';

    public string $quick_contact_mobile = '';

    public string $quick_contact_email = '';

    public string $quick_contact_job_title = '';

    public function openQuickCustomerModal(): void
    {
        $this->resetQuickCustomerForm();
        $this->showQuickCustomerModal = true;
    }

    public function closeQuickCustomerModal(): void
    {
        $this->showQuickCustomerModal = false;
        $this->resetQuickCustomerForm();
    }

    public function saveQuickCustomer(PartyCreationService $parties): void
    {
        $this->validate([
            'quick_customer_name' => ['required', 'string', 'max:255'],
            'quick_customer_kind' => ['required', 'in:company,person'],
            'quick_customer_mobile' => ['nullable', 'string', 'max:20'],
            'quick_customer_phone' => ['nullable', 'string', 'max:20'],
            'quick_customer_email' => ['nullable', 'email', 'max:255'],
        ], [], [
            'quick_customer_name' => 'نام مشتری',
            'quick_customer_kind' => 'نوع',
            'quick_customer_mobile' => 'موبایل',
            'quick_customer_phone' => 'تلفن',
            'quick_customer_email' => 'ایمیل',
        ]);

        try {
            $party = $parties->createCustomer([
                'name' => $this->quick_customer_name,
                'kind' => $this->quick_customer_kind,
                'mobile' => $this->quick_customer_mobile ?: null,
                'phone' => $this->quick_customer_phone ?: null,
                'email' => $this->quick_customer_email ?: null,
            ], auth()->user());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError('quick_customer_'.$field, $messages[0]);
            }

            return;
        }

        $this->form_party_id = (string) $party->id;
        $this->form_contact_id = '';
        $this->closeQuickCustomerModal();
        session()->flash('success', 'مشتری جدید ثبت و انتخاب شد.');
    }

    public function openQuickContactModal(): void
    {
        if ($this->form_party_id === '') {
            $this->addError('form_party_id', 'ابتدا مشتری را انتخاب کنید یا مشتری جدید بسازید.');

            return;
        }

        $this->resetQuickContactForm();
        $this->showQuickContactModal = true;
    }

    public function closeQuickContactModal(): void
    {
        $this->showQuickContactModal = false;
        $this->resetQuickContactForm();
    }

    public function saveQuickContact(CrmContactService $contacts): void
    {
        if ($this->form_party_id === '') {
            $this->addError('form_party_id', 'ابتدا مشتری را انتخاب کنید.');

            return;
        }

        $this->validate([
            'quick_contact_first_name' => ['required', 'string', 'max:255'],
            'quick_contact_last_name' => ['nullable', 'string', 'max:255'],
            'quick_contact_mobile' => ['nullable', 'string', 'max:20'],
            'quick_contact_email' => ['nullable', 'email', 'max:255'],
            'quick_contact_job_title' => ['nullable', 'string', 'max:255'],
        ], [], [
            'quick_contact_first_name' => 'نام',
            'quick_contact_last_name' => 'نام خانوادگی',
            'quick_contact_mobile' => 'موبایل',
            'quick_contact_email' => 'ایمیل',
            'quick_contact_job_title' => 'سمت',
        ]);

        try {
            $contact = $contacts->create([
                'party_id' => (int) $this->form_party_id,
                'first_name' => $this->quick_contact_first_name,
                'last_name' => $this->quick_contact_last_name,
                'mobile' => $this->quick_contact_mobile ?: null,
                'email' => $this->quick_contact_email ?: null,
                'job_title' => $this->quick_contact_job_title ?: null,
            ], auth()->user());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError('quick_contact_'.$field, $messages[0]);
            }

            return;
        }

        $this->form_contact_id = (string) $contact->id;
        $this->closeQuickContactModal();
        session()->flash('success', 'مخاطب جدید ثبت و انتخاب شد.');
    }

    public function openOpportunityCreate(): void
    {
        $this->resetOpportunityForm();

        $defaultPipeline = Pipeline::query()->where('is_default', true)->with('stages')->first();
        if ($defaultPipeline) {
            $this->form_pipeline_id = (string) $defaultPipeline->id;
            $this->form_stage_id = (string) ($defaultPipeline->stages->first()?->id ?? '');
        }

        $this->form_assigned_user_id = (string) auth()->id();
        $this->showOpportunityModal = true;
    }

    public function openOpportunityEdit(int $id): void
    {
        $opportunity = Opportunity::query()->with(['stage'])->findOrFail($id);

        $this->editingOpportunityId = $opportunity->id;
        $this->form_title = $opportunity->title;
        $this->form_party_id = (string) $opportunity->party_id;
        $this->form_contact_id = $opportunity->contact_id ? (string) $opportunity->contact_id : '';
        $this->form_pipeline_id = (string) $opportunity->pipeline_id;
        $this->form_stage_id = (string) $opportunity->stage_id;
        $this->form_assigned_user_id = (string) $opportunity->assigned_user_id;
        $this->form_amount = moneyInputValue($opportunity->amount, 0);
        $this->form_probability = (string) (float) $opportunity->probability;
        $this->form_expected_close_date = jalaliDateInputValue($opportunity->expected_close_date);
        $this->form_source_id = $opportunity->source_id ? (string) $opportunity->source_id : '';
        $this->form_description = $opportunity->description ?? '';
        $this->showOpportunityModal = true;
    }

    public function closeOpportunityModal(): void
    {
        $this->showOpportunityModal = false;
        $this->closeQuickCustomerModal();
        $this->closeQuickContactModal();
        $this->resetOpportunityForm();
    }

    public function updatedFormPartyId(): void
    {
        $this->form_contact_id = '';
    }

    public function updatedFormPipelineId(): void
    {
        if ($this->form_pipeline_id === '') {
            $this->form_stage_id = '';

            return;
        }

        $stage = PipelineStage::query()
            ->where('pipeline_id', (int) $this->form_pipeline_id)
            ->orderBy('sort_order')
            ->first();

        $this->form_stage_id = $stage ? (string) $stage->id : '';
    }

    public function saveOpportunity(CrmOpportunityService $opportunities): void
    {
        $isEditing = (bool) $this->editingOpportunityId;
        $opportunity = $isEditing
            ? Opportunity::query()->findOrFail($this->editingOpportunityId)
            : null;

        if (! $this->normalizeOpportunityMoneyFields()) {
            return;
        }

        $rules = [
            'form_title' => ['required', 'string', 'max:255'],
            'form_party_id' => ['required', 'exists:parties,id'],
            'form_contact_id' => ['nullable', 'exists:crm_contacts,id'],
            'form_pipeline_id' => ['required', 'exists:crm_pipelines,id'],
            'form_stage_id' => ['required', 'exists:crm_pipeline_stages,id'],
            'form_assigned_user_id' => ['required', 'exists:users,id'],
            'form_amount' => ['required', 'numeric', 'min:0'],
            'form_probability' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'form_expected_close_date' => ['nullable', 'string'],
            'form_source_id' => ['nullable', 'exists:crm_lead_sources,id'],
            'form_description' => ['required', 'string', 'min:10', 'max:10000'],
        ];

        if ($isEditing && $opportunity?->status !== 'open') {
            unset($rules['form_pipeline_id'], $rules['form_stage_id']);
        }

        $this->validate($rules, [], [
            'form_title' => 'عنوان',
            'form_party_id' => 'مشتری',
            'form_contact_id' => 'مخاطب',
            'form_pipeline_id' => 'پایپ‌لاین',
            'form_stage_id' => 'مرحله',
            'form_assigned_user_id' => 'مسئول',
            'form_amount' => 'مبلغ',
            'form_probability' => 'احتمال',
            'form_expected_close_date' => 'تاریخ پیش‌بینی بسته‌شدن',
            'form_source_id' => 'منبع',
            'form_description' => 'توضیحات',
        ]);

        if ($this->form_expected_close_date !== '' && ! jalaliToGregorianDate($this->form_expected_close_date)) {
            $this->addError('form_expected_close_date', 'تاریخ پیش‌بینی بسته‌شدن معتبر نیست.');

            return;
        }

        $data = [
            'title' => $this->form_title,
            'party_id' => (int) $this->form_party_id,
            'contact_id' => $this->form_contact_id !== '' ? (int) $this->form_contact_id : null,
            'assigned_user_id' => (int) $this->form_assigned_user_id,
            'amount' => (float) $this->form_amount,
            'probability' => $this->form_probability !== '' ? (float) $this->form_probability : 0,
            'expected_close_date' => $this->form_expected_close_date !== '' ? jalaliToGregorianDate($this->form_expected_close_date) : null,
            'source_id' => $this->form_source_id !== '' ? (int) $this->form_source_id : null,
            'description' => $this->form_description ?: null,
        ];

        $actor = auth()->user();

        if ($isEditing) {
            if ($opportunity->status === 'open') {
                $data['pipeline_id'] = (int) $this->form_pipeline_id;
                $data['stage_id'] = (int) $this->form_stage_id;
            }

            $opportunities->update($opportunity, $data, $actor);
            session()->flash('success', 'فرصت به‌روزرسانی شد.');
        } else {
            $data['pipeline_id'] = (int) $this->form_pipeline_id;
            $data['stage_id'] = (int) $this->form_stage_id;
            $opportunities->create($data, $actor);
            session()->flash('success', 'فرصت جدید ثبت شد.');
        }

        $this->closeOpportunityModal();
    }

    public function deleteOpportunity(
        int $opportunityId,
        CrmOpportunityService $opportunities,
        CrmOpportunityRepository $opportunityRepository,
    ): void {
        $user = auth()->user();

        if (! $user?->hasPermission('crm.opportunities.delete')) {
            session()->flash('error', 'مجوز حذف فرصت را ندارید.');

            return;
        }

        $opportunity = $opportunityRepository->findAccessible($opportunityId, $user);

        if (! $opportunity) {
            session()->flash('error', 'فرصت یافت نشد یا به آن دسترسی ندارید.');

            return;
        }

        try {
            $opportunities->deleteOpen($opportunity, $user);
            session()->flash('success', 'فرصت «'.$opportunity->title.'» حذف شد.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function reopenFromWon(int $opportunityId, CrmOpportunityService $opportunities): void
    {
        $user = auth()->user();

        if (! $user?->hasPermission('crm.opportunities.move_stage')) {
            session()->flash('error', 'مجوز بازگرداندن فرصت از وضعیت برنده را ندارید.');

            return;
        }

        $opportunity = Opportunity::query()->findOrFail($opportunityId);

        try {
            $restored = $opportunities->reopenFromWon($opportunity, $user);
            session()->flash(
                'success',
                'فرصت به مرحله «'.$restored->stage?->name.'» برگردانده شد و دوباره باز است.',
            );
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    protected function opportunityFormOptions(): array
    {
        $parties = Party::query()->customers()->orderBy('name')->limit(300)->get(['id', 'name']);
        $pipelines = Pipeline::query()->where('is_active', true)->with('stages')->orderBy('sort_order')->get();
        $sources = LeadSource::query()->where('is_active', true)->orderBy('sort_order')->get();
        $users = User::query()->orderBy('name')->get(['id', 'name']);

        $contacts = $this->form_party_id !== ''
            ? Contact::query()
                ->where('party_id', (int) $this->form_party_id)
                ->where('is_active', true)
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
            : collect();

        $stages = $this->form_pipeline_id !== ''
            ? PipelineStage::query()
                ->where('pipeline_id', (int) $this->form_pipeline_id)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'probability'])
            : collect();

        $editingOpportunity = $this->editingOpportunityId
            ? Opportunity::query()->find($this->editingOpportunityId)
            : null;

        $selectedParty = $this->form_party_id !== ''
            ? Party::query()->find((int) $this->form_party_id)
            : null;

        return compact('parties', 'pipelines', 'sources', 'users', 'contacts', 'stages', 'editingOpportunity', 'selectedParty');
    }

    private function resetQuickCustomerForm(): void
    {
        $this->reset([
            'quick_customer_name', 'quick_customer_mobile', 'quick_customer_phone', 'quick_customer_email',
        ]);
        $this->quick_customer_kind = 'company';
        $this->resetValidation([
            'quick_customer_name', 'quick_customer_kind', 'quick_customer_mobile', 'quick_customer_phone', 'quick_customer_email',
        ]);
    }

    private function resetQuickContactForm(): void
    {
        $this->reset([
            'quick_contact_first_name', 'quick_contact_last_name', 'quick_contact_mobile',
            'quick_contact_email', 'quick_contact_job_title',
        ]);
        $this->resetValidation([
            'quick_contact_first_name', 'quick_contact_last_name', 'quick_contact_mobile',
            'quick_contact_email', 'quick_contact_job_title',
        ]);
    }

    private function normalizeOpportunityMoneyFields(): bool
    {
        if (trim($this->form_amount) !== '') {
            $amount = normalizeMoneyValue($this->form_amount);

            if ($amount === null) {
                $this->addError('form_amount', 'مبلغ معتبر نیست.');

                return false;
            }

            $this->form_amount = (string) $amount;
        }

        if (trim($this->form_probability) !== '') {
            $probability = normalizeMoneyValue($this->form_probability);

            if ($probability === null) {
                $this->addError('form_probability', 'احتمال معتبر نیست.');

                return false;
            }

            $this->form_probability = (string) $probability;
        }

        return true;
    }

    private function resetOpportunityForm(): void
    {
        $this->editingOpportunityId = null;
        $this->reset([
            'form_title', 'form_party_id', 'form_contact_id', 'form_pipeline_id', 'form_stage_id',
            'form_assigned_user_id', 'form_amount', 'form_probability', 'form_expected_close_date',
            'form_source_id', 'form_description',
        ]);
        $this->resetValidation();
    }
}
