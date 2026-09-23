<?php

namespace App\Livewire\WorkLogs;

use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkLog;
use Hekmatinasser\Verta\Verta;
use Livewire\Component;
use Livewire\WithPagination;

class Manager extends Component
{
    use WithPagination;

    public string $employeeId = '';
    public string $project = '';
    public string $startDateFa = '';
    public string $endDateFa = '';
    public string $statusFilter = '';
    public int $perPage = 50;

    /** @var list<int|string> */
    public array $selected = [];

    public string $bulkProjectId = '';

    public bool $selectAll = false;

    public bool $showBulkProjectModal = false;

    public bool $showDeleteConfirmModal = false;

    public ?int $pendingDeleteId = null;

    public string $deleteConfirmMessage = '';

    protected $queryString = [
        'employeeId' => ['except' => '', 'as' => 'employee'],
        'project' => ['except' => ''],
        'startDateFa' => ['except' => ''],
        'endDateFa' => ['except' => ''],
        'statusFilter' => ['except' => '', 'as' => 'status'],
        'page' => ['except' => 1],
    ];

    public function updated($name): void
    {
        if (in_array($name, ['employeeId', 'project', 'startDateFa', 'endDateFa', 'statusFilter', 'perPage'], true)) {
            $this->selected = [];
            $this->selectAll = false;
            $this->resetPage();
        }
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selected = $this->filteredQuery()
                ->orderByDesc('work_date')
                ->orderByDesc('start_time')
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
        } else {
            $this->selected = [];
        }
    }

    public function clearFilters(): void
    {
        $this->reset([
            'employeeId',
            'project',
            'startDateFa',
            'endDateFa',
            'statusFilter',
            'selected',
            'selectAll',
            'bulkProjectId',
            'showBulkProjectModal',
            'showDeleteConfirmModal',
            'pendingDeleteId',
            'deleteConfirmMessage',
        ]);
        $this->resetPage();
    }

    public function updateProject(int $workLogId, string $projectId): void
    {
        $this->updateField($workLogId, 'project_id', $projectId);
    }

    public function updateField(int $workLogId, string $field, string $value): void
    {
        $workLog = WorkLog::with('employee')->findOrFail($workLogId);
        $value = trim($value);
        $payload = [];

        switch ($field) {
            case 'project_id':
                $payload['project_id'] = $value === '' ? null : (int) $value;
                break;

            case 'work_date':
                $gregorian = function_exists('jalaliToGregorianDate')
                    ? jalaliToGregorianDate($value)
                    : null;
                if (! $gregorian) {
                    try {
                        $gregorian = Verta::parse(str_replace('/', '-', $value))->datetime()->format('Y-m-d');
                    } catch (\Throwable) {
                        session()->flash('error', 'تاریخ معتبر نیست.');

                        return;
                    }
                }
                $payload['work_date'] = $gregorian;
                break;

            case 'start_time':
                $time = $this->normalizeTime($value);
                if ($value !== '' && $time === null) {
                    session()->flash('error', 'ساعت ورود معتبر نیست.');

                    return;
                }
                $payload['start_time'] = $time;
                $payload['check_in_time'] = $time;
                break;

            case 'end_time':
                $time = $this->normalizeTime($value);
                if ($value !== '' && $time === null) {
                    session()->flash('error', 'ساعت خروج معتبر نیست.');

                    return;
                }
                $payload['end_time'] = $time;
                $payload['check_out_time'] = $time;
                break;

            case 'hours':
                $normalized = function_exists('normalizePersianDigits')
                    ? normalizePersianDigits($value)
                    : $value;
                if ($normalized === '' || ! is_numeric($normalized) || (float) $normalized < 0) {
                    session()->flash('error', 'ساعت کار معتبر نیست.');

                    return;
                }
                $hours = round((float) $normalized, 2);
                $payload['hours'] = $hours;
                $payload['total_amount'] = $hours * (float) ($workLog->hourly_rate ?: 0);
                if ($hours > 0 && $workLog->start_time && $workLog->end_time) {
                    $payload['is_incomplete'] = false;
                }
                break;

            default:
                return;
        }

        if (in_array($field, ['start_time', 'end_time'], true)) {
            $start = $payload['start_time'] ?? ($workLog->start_time ? substr((string) $workLog->start_time, 0, 5) : null);
            $end = array_key_exists('end_time', $payload)
                ? $payload['end_time']
                : ($workLog->end_time ? substr((string) $workLog->end_time, 0, 5) : null);

            $isIncomplete = blank($start) || blank($end);
            $payload['is_incomplete'] = $isIncomplete;

            if (! $isIncomplete) {
                try {
                    $startAt = \Carbon\Carbon::createFromFormat('H:i', $start);
                    $endAt = \Carbon\Carbon::createFromFormat('H:i', $end);
                    if ($endAt->lessThan($startAt)) {
                        $endAt->addDay();
                    }
                    $hours = round($startAt->diffInMinutes($endAt) / 60, 2);
                    $payload['hours'] = $hours;
                    $payload['total_amount'] = $hours * (float) ($workLog->hourly_rate ?: 0);
                } catch (\Throwable) {
                }
            } else {
                $payload['hours'] = 0;
                $payload['total_amount'] = 0;
            }
        }

        $workLog->update($payload);
    }

    private function normalizeTime(string $value): ?string
    {
        $value = function_exists('normalizePersianDigits')
            ? (string) normalizePersianDigits(trim($value))
            : trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches)) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return null;
    }

    public function openDeleteConfirm(?int $workLogId = null): void
    {
        if ($workLogId !== null) {
            $this->pendingDeleteId = $workLogId;
            $this->deleteConfirmMessage = 'این کارکرد حذف شود؟';
            $this->showDeleteConfirmModal = true;

            return;
        }

        $ids = $this->selectedIds();
        if ($ids === []) {
            session()->flash('error', 'ابتدا حداقل یک ردیف را انتخاب کنید.');

            return;
        }

        $this->pendingDeleteId = null;
        $count = toPersianDigits(count($ids));
        $this->deleteConfirmMessage = "{$count} کارکرد انتخاب‌شده حذف شوند؟";
        $this->showDeleteConfirmModal = true;
    }

    public function closeDeleteConfirm(): void
    {
        $this->showDeleteConfirmModal = false;
        $this->pendingDeleteId = null;
        $this->deleteConfirmMessage = '';
    }

    public function confirmDelete(): void
    {
        if ($this->pendingDeleteId !== null) {
            $workLogId = $this->pendingDeleteId;
            $this->closeDeleteConfirm();
            $this->removeWorkLog($workLogId);

            return;
        }

        $this->closeDeleteConfirm();
        $this->bulkDelete();
    }

    public function removeWorkLog(int $workLogId): void
    {
        WorkLog::findOrFail($workLogId)->delete();
        $this->selected = array_values(array_filter($this->selected, fn ($id) => (int) $id !== $workLogId));
        $this->resetPage();
        session()->flash('success', 'کارکرد حذف شد.');
    }

    public function bulkDelete(): void
    {
        $ids = $this->selectedIds();
        if ($ids === []) {
            session()->flash('error', 'ابتدا حداقل یک ردیف را انتخاب کنید.');

            return;
        }

        WorkLog::whereIn('id', $ids)->delete();
        $this->selected = [];
        $this->selectAll = false;
        $this->showBulkProjectModal = false;
        $this->resetPage();
        session()->flash('success', toPersianDigits(count($ids)) . ' کارکرد حذف شد.');
    }

    public function openBulkProjectModal(): void
    {
        if ($this->selectedIds() === []) {
            session()->flash('error', 'ابتدا حداقل یک ردیف را انتخاب کنید.');

            return;
        }

        $this->bulkProjectId = '';
        $this->showBulkProjectModal = true;
    }

    public function closeBulkProjectModal(): void
    {
        $this->showBulkProjectModal = false;
        $this->bulkProjectId = '';
    }

    public function bulkAssignProject(?int $projectId = null): void
    {
        $ids = $this->selectedIds();
        if ($ids === []) {
            session()->flash('error', 'ابتدا حداقل یک ردیف را انتخاب کنید.');
            $this->showBulkProjectModal = false;

            return;
        }

        $resolvedId = $projectId ?? ($this->bulkProjectId !== '' ? (int) $this->bulkProjectId : null);
        if (! $resolvedId) {
            session()->flash('error', 'پروژه را انتخاب کنید.');

            return;
        }

        WorkLog::whereIn('id', $ids)->update([
            'project_id' => $resolvedId,
        ]);

        $this->selected = [];
        $this->selectAll = false;
        $this->bulkProjectId = '';
        $this->showBulkProjectModal = false;
        session()->flash('success', count($ids) . ' کارکرد به پروژه انتخاب‌شده تخصیص داده شد.');
    }

    public function render()
    {
        $workLogs = $this->filteredQuery()
            ->orderByDesc('work_date')
            ->orderByDesc('start_time')
            ->paginate($this->perPage);

        $projects = Project::orderBy('name')->get();
        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'personnel_code', 'employee_code']);

        return view('livewire.work-logs.manager', compact('workLogs', 'projects', 'employees'));
    }

    private function filteredQuery()
    {
        $query = WorkLog::with(['employee', 'project']);

        $user = auth()->user();
        if ($user && ! $user->isAdmin()) {
            $query->whereIn('employee_id', $user->accessibleEmployeeIds('worklogs.view'));
        }

        if ($this->employeeId !== '') {
            $query->where('employee_id', (int) $this->employeeId);
        }

        if ($this->project === 'none') {
            $query->whereNull('project_id');
        } elseif ($this->project !== '') {
            $query->where('project_id', $this->project);
        }

        if ($this->statusFilter === 'incomplete') {
            $query->where('is_incomplete', true);
        } elseif ($this->statusFilter === 'complete') {
            $query->where(function ($q) {
                $q->where('is_incomplete', false)->orWhereNull('is_incomplete');
            });
        }

        if ($this->startDateFa !== '') {
            $from = function_exists('jalaliToGregorianDate')
                ? jalaliToGregorianDate($this->startDateFa)
                : null;
            if ($from) {
                $query->whereDate('work_date', '>=', $from);
            }
        }

        if ($this->endDateFa !== '') {
            $to = function_exists('jalaliToGregorianDate')
                ? jalaliToGregorianDate($this->endDateFa)
                : null;
            if ($to) {
                $query->whereDate('work_date', '<=', $to);
            }
        }

        return $query;
    }

    /** @return list<int> */
    private function selectedIds(): array
    {
        return collect($this->selected)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
