<?php

namespace App\Livewire\Crm\Activities;

use App\Livewire\Core\UI\BaseListPage;
use App\Livewire\Crm\Concerns\ManagesCrmActivities;
use App\Models\Crm\Activity;
use App\Models\Crm\CrmModel;
use App\Models\Party;
use App\Repositories\Crm\CrmActivityRepository;
use App\Services\Crm\CrmActivityService;

class Index extends BaseListPage
{
    use ManagesCrmActivities;

    public string $search = '';

    public string $type = '';

    public string $status = '';

    public string $form_party_id = '';

    protected array $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function openCreate(): void
    {
        $this->form_party_id = '';
        $this->openActivityModal();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'status']);
        $this->resetPage();
    }

    public function complete(int $id, CrmActivityService $activities): void
    {
        $activity = Activity::query()->findOrFail($id);
        $activities->complete($activity, auth()->user());
        session()->flash('success', 'فعالیت تکمیل شد.');
    }

    public function storeActivity(CrmActivityService $activities): void
    {
        if ($this->form_party_id !== '') {
            $this->activity_party_id = (int) $this->form_party_id;
        }

        $this->saveActivity($activities);
    }

    public function render(CrmActivityRepository $activities)
    {
        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'type' => $this->type !== '' ? $this->type : null,
            'status' => $this->status !== '' ? $this->status : null,
        ]);

        $items = $activities->paginate($filters, auth()->user(), $this->perPage);
        $typeOptions = CrmModel::ACTIVITY_TYPES;
        $customers = Party::query()->customers()->orderBy('name')->limit(300)->get(['id', 'name', 'code']);

        return view('livewire.crm.activities.index', compact('items', 'typeOptions', 'customers'));
    }
}
