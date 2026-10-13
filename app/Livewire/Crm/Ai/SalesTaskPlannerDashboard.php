<?php

namespace App\Livewire\Crm\Ai;

use App\Models\Crm\SalesTaskPlan;
use App\Models\Crm\SalesTaskSuggestion;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Crm\SalesTaskPlannerService;
use Livewire\Component;

class SalesTaskPlannerDashboard extends Component
{
    public ?string $message = null;

    public ?string $messageType = null;

    public function requestPlan(SalesTaskPlannerService $service): void
    {
        try {
            $service->request(auth()->user());
            $this->flash('درخواست تولید برنامه پیگیری ثبت شد.', 'success');
        } catch (AiException $e) {
            $this->flash($e->getMessage(), 'error');
        }
    }

    public function applySuggestion(int $suggestionId, SalesTaskPlannerService $service): void
    {
        $suggestion = SalesTaskSuggestion::query()->with('plan')->findOrFail($suggestionId);

        try {
            $task = $service->apply($suggestion, auth()->user());
            $this->flash('وظیفه ایجاد/به‌روزرسانی شد: '.$task->title, 'success');
        } catch (AiException $e) {
            $this->flash($e->getMessage(), 'error');
        }
    }

    public function rejectSuggestion(int $suggestionId, SalesTaskPlannerService $service): void
    {
        $suggestion = SalesTaskSuggestion::query()->with('plan')->findOrFail($suggestionId);

        try {
            $service->reject($suggestion, auth()->user());
            $this->flash('پیشنهاد رد شد.', 'success');
        } catch (AiException $e) {
            $this->flash($e->getMessage(), 'error');
        }
    }

    public function render(SalesTaskPlannerService $service)
    {
        $user = auth()->user();
        abort_unless($service->canRequest($user) || $user->hasPermission('crm.tasks.view') || $user->isAdmin(), 403);

        $dashboard = $service->dashboard($user);
        $plan = $dashboard['latest_plan'];

        return view('livewire.crm.ai.sales-task-planner-dashboard', [
            'groups' => $dashboard['groups'],
            'plan' => $plan,
            'openTaskCount' => $dashboard['open_task_count'],
            'pendingSuggestionCount' => $dashboard['pending_suggestion_count'],
            'aiEnabled' => $service->isEnabled(),
            'canRequest' => $service->canRequest($user),
            'canApply' => $service->canApply($user),
            'isPending' => $plan?->status === SalesTaskPlan::STATUS_PENDING,
        ]);
    }

    private function flash(string $message, string $type): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }
}
