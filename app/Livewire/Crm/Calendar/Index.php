<?php

namespace App\Livewire\Crm\Calendar;

use App\Repositories\Crm\CrmActivityRepository;
use App\Repositories\Crm\CrmTaskRepository;
use Carbon\Carbon;
use Livewire\Component;

class Index extends Component
{
    public string $viewMode = 'month';

    public int $year;

    public int $month;

    public int $weekOffset = 0;

    protected $queryString = [
        'viewMode' => ['except' => 'month'],
    ];

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['month', 'week'], true) ? $mode : 'month';
    }

    public function previousPeriod(): void
    {
        if ($this->viewMode === 'week') {
            $this->weekOffset--;

            return;
        }

        $this->month--;
        if ($this->month < 1) {
            $this->month = 12;
            $this->year--;
        }
    }

    public function nextPeriod(): void
    {
        if ($this->viewMode === 'week') {
            $this->weekOffset++;

            return;
        }

        $this->month++;
        if ($this->month > 12) {
            $this->month = 1;
            $this->year++;
        }
    }

    public function render(CrmActivityRepository $activities, CrmTaskRepository $tasks)
    {
        $user = auth()->user();

        if ($this->viewMode === 'week') {
            $start = now()->startOfWeek()->addWeeks($this->weekOffset);
            $end = (clone $start)->endOfWeek();
            $periodLabel = gregorianToJalaliDate($start->toDateString()) . ' — ' . gregorianToJalaliDate($end->toDateString());
        } else {
            $start = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $periodLabel = gregorianToJalaliDate($start->toDateString()) . ' — ' . gregorianToJalaliDate($end->toDateString());
        }

        $activityItems = $activities->forCalendar($user, $start->startOfDay()->toDateTimeString(), $end->endOfDay()->toDateTimeString());
        $taskItems = $tasks->forCalendar($user, $start->startOfDay()->toDateTimeString(), $end->endOfDay()->toDateTimeString());

        $events = collect()
            ->merge($activityItems->map(fn ($a) => [
                'id' => 'activity-' . $a->id,
                'kind' => 'activity',
                'title' => $a->subject,
                'subtitle' => $a->type_label,
                'party' => $a->party?->name,
                'at' => $a->due_at,
            ]))
            ->merge($taskItems->map(fn ($t) => [
                'id' => 'task-' . $t->id,
                'kind' => 'task',
                'title' => $t->title,
                'subtitle' => $t->priority_label,
                'party' => $t->party?->name,
                'at' => $t->due_at,
            ]))
            ->filter(fn ($e) => $e['at'] !== null)
            ->sortBy('at')
            ->groupBy(fn ($e) => $e['at']->format('Y-m-d'));

        return view('livewire.crm.calendar.index', compact('events', 'periodLabel', 'start', 'end'));
    }
}
