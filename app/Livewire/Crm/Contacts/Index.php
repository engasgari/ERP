<?php

namespace App\Livewire\Crm\Contacts;

use App\Livewire\Core\UI\BaseListPage;
use App\Models\Crm\Contact;
use App\Models\Party;
use App\Repositories\Crm\CrmContactRepository;
use App\Services\Crm\CrmContactService;

class Index extends BaseListPage
{
    public string $search = '';

    public string $status = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $party_id = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $mobile = '';

    public string $email = '';

    public string $job_title = '';

    protected array $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $contact = Contact::query()->findOrFail($id);
        $this->editingId = $contact->id;
        $this->party_id = (string) $contact->party_id;
        $this->first_name = $contact->first_name;
        $this->last_name = $contact->last_name ?? '';
        $this->mobile = $contact->mobile ?? '';
        $this->email = $contact->email ?? '';
        $this->job_title = $contact->job_title ?? '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(CrmContactService $contacts): void
    {
        $this->validate([
            'party_id' => ['required', 'exists:parties,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ], [], [
            'party_id' => 'مشتری',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'mobile' => 'موبایل',
            'email' => 'ایمیل',
            'job_title' => 'سمت',
        ]);

        $data = [
            'party_id' => (int) $this->party_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'mobile' => $this->mobile ?: null,
            'email' => $this->email ?: null,
            'job_title' => $this->job_title ?: null,
        ];

        $actor = auth()->user();

        if ($this->editingId) {
            $contact = Contact::query()->findOrFail($this->editingId);
            $contacts->update($contact, $data, $actor);
            session()->flash('success', 'مخاطب به‌روزرسانی شد.');
        } else {
            $contacts->create($data, $actor);
            session()->flash('success', 'مخاطب جدید ثبت شد.');
        }

        $this->closeModal();
    }

    public function remove(int $id, CrmContactService $contacts): void
    {
        $contact = Contact::query()->findOrFail($id);
        $result = $contacts->remove($contact, auth()->user());
        session()->flash('success', $result->flashMessage());
    }

    public function reactivate(int $id, CrmContactService $contacts): void
    {
        $contact = Contact::query()->findOrFail($id);
        $contacts->reactivate($contact, auth()->user());
        session()->flash('success', 'مخاطب فعال شد.');
    }

    public function render(CrmContactRepository $contacts)
    {
        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'status' => $this->status !== '' ? $this->status : null,
        ]);

        $items = $contacts->paginate($filters, auth()->user(), $this->perPage);
        $parties = Party::query()->customers()->orderBy('name')->limit(200)->get(['id', 'name']);

        return view('livewire.crm.contacts.index', compact('items', 'parties'));
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->reset(['party_id', 'first_name', 'last_name', 'mobile', 'email', 'job_title']);
        $this->resetValidation();
    }
}
