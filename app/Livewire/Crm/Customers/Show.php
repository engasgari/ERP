<?php

namespace App\Livewire\Crm\Customers;

use App\Livewire\Crm\Concerns\ManagesCrmActivities;
use App\Livewire\Crm\Concerns\ManagesCrmOpportunities;
use App\Livewire\Crm\Concerns\ManagesCrmTasks;
use App\Models\Crm\Contact;
use App\Models\Crm\CrmModel;
use App\Models\Crm\Opportunity;
use App\Models\Party;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Services\Crm\CrmContactService;
use App\Services\Crm\CrmCustomerService;
use App\Services\Crm\CrmTimelineService;
use Livewire\Component;

class Show extends Component
{
    use ManagesCrmActivities;
    use ManagesCrmOpportunities;
    use ManagesCrmTasks;

    private const TABS = [
        'overview', 'activities', 'tasks', 'contacts',
        'leads', 'opportunities', 'timeline', 'commerce',
    ];

    public int $partyId;

    public string $activeTab = 'overview';

    public ?int $timelineOpportunityFilter = null;

    public ?int $showingOpportunityTimelineId = null;

    public bool $showContactModal = false;

    public ?int $editingContactId = null;

    public string $contact_first_name = '';

    public string $contact_last_name = '';

    public string $contact_mobile = '';

    public string $contact_email = '';

    public string $contact_job_title = '';

    public function mount(int $partyId, string $activeTab = 'overview'): void
    {
        $this->partyId = $partyId;

        if (in_array($activeTab, self::TABS, true)) {
            $this->activeTab = $activeTab;
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->activeTab = $tab;
        }
    }

    public function openContactCreate(): void
    {
        abort_unless(auth()->user()?->hasPermission('crm.contacts.create'), 403);
        $this->resetContactForm();
        $this->showContactModal = true;
    }

    public function openContactEdit(int $id): void
    {
        abort_unless(auth()->user()?->hasPermission('crm.contacts.update'), 403);

        $contact = Contact::query()
            ->where('party_id', $this->partyId)
            ->findOrFail($id);

        $this->editingContactId = $contact->id;
        $this->contact_first_name = $contact->first_name;
        $this->contact_last_name = $contact->last_name ?? '';
        $this->contact_mobile = $contact->mobile ?? '';
        $this->contact_email = $contact->email ?? '';
        $this->contact_job_title = $contact->job_title ?? '';
        $this->showContactModal = true;
    }

    public function closeContactModal(): void
    {
        $this->showContactModal = false;
        $this->resetContactForm();
    }

    public function saveContact(CrmContactService $contacts): void
    {
        $isEdit = $this->editingContactId !== null;
        abort_unless(
            auth()->user()?->hasPermission($isEdit ? 'crm.contacts.update' : 'crm.contacts.create'),
            403
        );

        $this->validate([
            'contact_first_name' => ['required', 'string', 'max:255'],
            'contact_last_name' => ['nullable', 'string', 'max:255'],
            'contact_mobile' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_job_title' => ['nullable', 'string', 'max:255'],
        ], [], [
            'contact_first_name' => 'نام',
            'contact_last_name' => 'نام خانوادگی',
            'contact_mobile' => 'موبایل',
            'contact_email' => 'ایمیل',
            'contact_job_title' => 'سمت',
        ]);

        $data = [
            'party_id' => $this->partyId,
            'first_name' => $this->contact_first_name,
            'last_name' => $this->contact_last_name,
            'mobile' => $this->contact_mobile ?: null,
            'email' => $this->contact_email ?: null,
            'job_title' => $this->contact_job_title ?: null,
        ];

        $actor = auth()->user();

        if ($isEdit) {
            $contact = Contact::query()
                ->where('party_id', $this->partyId)
                ->findOrFail($this->editingContactId);
            $contacts->update($contact, $data, $actor);
            session()->flash('success', 'مخاطب به‌روزرسانی شد.');
        } else {
            $contacts->create($data, $actor);
            session()->flash('success', 'مخاطب جدید ثبت شد.');
        }

        $this->closeContactModal();
    }

    public function removeContact(int $id, CrmContactService $contacts): void
    {
        abort_unless(auth()->user()?->hasPermission('crm.contacts.delete'), 403);

        $contact = Contact::query()
            ->where('party_id', $this->partyId)
            ->findOrFail($id);

        $result = $contacts->remove($contact, auth()->user());
        session()->flash('success', $result->flashMessage());
    }

    public function openCustomerActivity(): void
    {
        $this->openActivityModal(partyId: $this->partyId);
    }

    public function openCustomerTask(): void
    {
        $this->openTaskModal(partyId: $this->partyId);
    }

    public function openOpportunityActivity(int $opportunityId): void
    {
        $opp = Opportunity::query()->findOrFail($opportunityId);

        $this->openActivityModal(
            partyId: $this->partyId,
            activitableType: Opportunity::class,
            activitableId: $opp->id,
        );
    }

    public function openOpportunityTimeline(int $opportunityId): void
    {
        $this->showingOpportunityTimelineId = $opportunityId;
    }

    public function closeOpportunityTimeline(): void
    {
        $this->showingOpportunityTimelineId = null;
    }

    public function updatedTimelineOpportunityFilter(mixed $value): void
    {
        $this->timelineOpportunityFilter = ($value === '' || $value === null) ? null : (int) $value;
    }

    public function deactivateCustomer(CrmCustomerService $customers): void
    {
        $party = Party::query()->customers()->findOrFail($this->partyId);
        $customers->deactivate($party, auth()->user());
        session()->flash('success', 'مشتری غیرفعال شد. سرنخ‌ها و وظایف مرتبط حذف و فرصت‌های باز بسته شدند.');
    }

    public function reactivateCustomer(CrmCustomerService $customers): void
    {
        $party = Party::query()->customers()->findOrFail($this->partyId);
        $customers->reactivate($party, auth()->user());
        session()->flash('success', 'مشتری فعال شد.');
    }

    public function render(CrmCustomerRepository $customers, CrmTimelineService $timeline)
    {
        $party = $customers->findForShow($this->partyId, auth()->user());

        abort_if(! $party, 404);

        $timelineTree = $this->activeTab === 'timeline'
            ? $timeline->forPartyTree($party, $this->timelineOpportunityFilter)
            : collect();

        $timelineEntries = $this->activeTab === 'timeline'
            ? $timeline->flattenTreeEntries($timelineTree)
            : collect();

        $timelineSummary = $this->activeTab === 'timeline'
            ? $timeline->partySummary($timelineEntries)
            : ['calls' => 0, 'sms' => 0, 'stages' => 0, 'activities' => 0];

        $timelineOpportunityOptions = $timeline->opportunityFilterOptions($party);

        $opportunityTimelineOverview = null;

        if ($this->showingOpportunityTimelineId) {
            $timelineOpportunity = $party->crmOpportunities
                ->firstWhere('id', $this->showingOpportunityTimelineId);

            if ($timelineOpportunity) {
                $opportunityTimelineOverview = $timeline->opportunityOverview($timelineOpportunity);
            } else {
                $this->showingOpportunityTimelineId = null;
            }
        }

        $statusOptions = CrmModel::CUSTOMER_STATUSES;
        $typeOptions = CrmModel::ACTIVITY_TYPES;
        $priorityOptions = CrmModel::PRIORITIES_TASK;

        return view('livewire.crm.customers.show', array_merge(
            compact(
                'party',
                'timelineEntries',
                'timelineTree',
                'timelineSummary',
                'timelineOpportunityOptions',
                'opportunityTimelineOverview',
                'statusOptions',
                'typeOptions',
                'priorityOptions',
            ),
            $this->opportunityFormOptions(),
            $this->taskFormOptions(),
        ));
    }

    private function resetContactForm(): void
    {
        $this->editingContactId = null;
        $this->reset([
            'contact_first_name',
            'contact_last_name',
            'contact_mobile',
            'contact_email',
            'contact_job_title',
        ]);
        $this->resetValidation([
            'contact_first_name',
            'contact_last_name',
            'contact_mobile',
            'contact_email',
            'contact_job_title',
        ]);
    }
}
