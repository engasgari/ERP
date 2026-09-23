<?php

namespace App\Livewire\Crm\Concerns;

use App\Models\Crm\Activity;
use App\Services\Crm\CrmActivityService;
use Carbon\Carbon;

trait ManagesCrmActivities
{
    public bool $showActivityModal = false;

    public ?int $editingActivityId = null;

    public string $activity_type = 'call';

    public string $activity_subject = '';

    public string $activity_description = '';

    public string $activity_due_at = '';

    public string $activity_completed_at = '';

    public string $activity_status = 'completed';

    public ?int $activity_party_id = null;

    public ?string $activity_activitable_type = null;

    public ?int $activity_activitable_id = null;

    public function openActivityModal(
        ?int $partyId = null,
        ?string $activitableType = null,
        ?int $activitableId = null,
        string $defaultType = 'call',
    ): void {
        $this->resetActivityForm();
        $this->activity_party_id = $partyId;
        $this->activity_activitable_type = $activitableType;
        $this->activity_activitable_id = $activitableId;
        $this->activity_type = $defaultType;
        $this->showActivityModal = true;
    }

    public function closeActivityModal(): void
    {
        $this->showActivityModal = false;
        $this->resetActivityForm();
    }

    public function openActivityEdit(int $id): void
    {
        if (! auth()->user()?->hasPermission('crm.activities.update')) {
            session()->flash('error', 'مجوز ویرایش فعالیت را ندارید.');

            return;
        }

        $activity = Activity::query()->findOrFail($id);

        $this->editingActivityId = $activity->id;
        $this->activity_type = $activity->type;
        $this->activity_subject = $activity->subject;
        $this->activity_description = $activity->description ?? '';
        $this->activity_status = $activity->status;
        $this->activity_due_at = jalaliDateTimeInputValue($activity->due_at);
        $this->activity_completed_at = $activity->completed_at
            ? jalaliDateTimeInputValue($activity->completed_at)
            : ($activity->status === 'completed' ? jalaliDateTimeInputValue(now()) : '');
        $this->activity_party_id = $activity->party_id;
        $this->activity_activitable_type = $activity->activitable_type;
        $this->activity_activitable_id = $activity->activitable_id;

        if (property_exists($this, 'form_party_id')) {
            $this->form_party_id = $activity->party_id ? (string) $activity->party_id : '';
        }

        $this->showActivityModal = true;
    }

    public function saveActivity(CrmActivityService $activities): void
    {
        $this->validate([
            'activity_type' => ['required', 'string'],
            'activity_subject' => ['required', 'string', 'max:255'],
            'activity_description' => ['nullable', 'string'],
            'activity_due_at' => ['nullable', 'string'],
            'activity_completed_at' => ['nullable', 'string'],
            'activity_status' => ['required', 'in:planned,completed'],
        ], [], [
            'activity_subject' => 'موضوع',
            'activity_description' => 'خلاصه/توضیحات',
            'activity_type' => 'نوع',
            'activity_due_at' => 'موعد',
            'activity_completed_at' => 'تاریخ انجام',
        ]);

        $dueAt = $this->parseActivityDueAt($this->activity_due_at);

        if ($this->activity_due_at !== '' && ! $dueAt) {
            $this->addError('activity_due_at', 'موعد معتبر نیست.');

            return;
        }

        if ($this->activity_status === 'planned' && ! $dueAt) {
            $this->addError('activity_due_at', 'برای فعالیت برنامه‌ریزی‌شده، موعد الزامی است.');

            return;
        }

        $completedAt = $this->parseActivityDueAt($this->activity_completed_at);

        if ($this->activity_completed_at !== '' && ! $completedAt) {
            $this->addError('activity_completed_at', 'تاریخ انجام معتبر نیست.');

            return;
        }

        if ($this->activity_status === 'completed' && ! $completedAt) {
            $completedAt = now();
        }

        if ($this->activity_status !== 'completed') {
            $completedAt = null;
        }

        $payload = [
            'type' => $this->activity_type,
            'subject' => $this->activity_subject,
            'description' => $this->activity_description ?: null,
            'party_id' => $this->activity_party_id,
            'due_at' => $dueAt,
            'completed_at' => $completedAt,
            'status' => $this->activity_status,
        ];

        if ($this->editingActivityId) {
            if (! auth()->user()?->hasPermission('crm.activities.update')) {
                session()->flash('error', 'مجوز ویرایش فعالیت را ندارید.');

                return;
            }

            $activity = Activity::query()->findOrFail($this->editingActivityId);
            $activities->update($activity, $payload, auth()->user());
            session()->flash('success', 'فعالیت به‌روزرسانی شد.');
        } else {
            $activities->create(array_merge($payload, [
                'activitable_type' => $this->activity_activitable_type,
                'activitable_id' => $this->activity_activitable_id,
                'assigned_user_id' => auth()->id(),
            ]), auth()->user());
            session()->flash('success', 'فعالیت ثبت شد.');
        }

        $this->afterActivitySaved();
        $this->closeActivityModal();
    }

    protected function afterActivitySaved(): void
    {
    }

    protected function resetActivityForm(): void
    {
        $this->editingActivityId = null;
        $this->reset([
            'activity_type', 'activity_subject', 'activity_description',
            'activity_due_at', 'activity_completed_at', 'activity_status', 'activity_party_id',
            'activity_activitable_type', 'activity_activitable_id',
        ]);
        $this->activity_type = 'call';
        $this->activity_status = 'completed';
        $this->activity_completed_at = jalaliDateTimeInputValue(now());
        $this->resetValidation();
    }

    protected function parseActivityDueAt(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        $gregorian = jalaliToGregorianDateTime($value);

        if ($gregorian) {
            return Carbon::parse($gregorian);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
