<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use App\Services\NumberingService;
use App\Services\ProjectDeletionService;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with('party', 'manager');

        if ($request->filled('name')) {
            $query->where(function ($projectQuery) use ($request) {
                $projectQuery->where('name', 'like', '%' . $request->name . '%')
                    ->orWhere('project_number', 'like', '%' . $request->name . '%')
                    ->orWhere('description', 'like', '%' . $request->name . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $startDate = jalaliToGregorianDate($request->start_date);
            if ($startDate) {
                $query->whereDate('start_date', '>=', $startDate);
            }
        }

        if ($request->filled('end_date')) {
            $endDate = jalaliToGregorianDate($request->end_date);
            if ($endDate) {
                $query->whereDate('end_date', '<=', $endDate);
            }
        }

        $projects = $query->latest()->paginate(12)->withQueryString();

        $dateErrors = [
            'start_date' => $request->filled('start_date') && ! jalaliToGregorianDate($request->start_date) ? 'تاریخ شروع معتبر نیست.' : null,
            'end_date' => $request->filled('end_date') && ! jalaliToGregorianDate($request->end_date) ? 'تاریخ پایان معتبر نیست.' : null,
        ];

        return view('projects.index', compact('projects', 'dateErrors'));
    }

    public function create()
    {
        return view('projects.create', $this->formData(new Project(['status' => 'planning'])));
    }

    public function show(Project $project)
    {
        $project->load(['party', 'manager', 'financialTransactions', 'workLogs.employee', 'productionOrders.item']);

        return view('projects.show', compact('project'));
    }

    public function store(Request $request, NumberingService $numbering)
    {
        $request->merge([
            'start_date' => jalaliToGregorianDate($request->input('start_date')),
            'end_date' => jalaliToGregorianDate($request->input('end_date')),
        ]);

        $data = $this->validated($request);
        $data['project_number'] = $data['project_number'] ?: $numbering->next('project', 'PRJ-');
        $data['status'] = $data['status'] ?? 'planning';

        Project::create($data);

        return redirect()->route('projects.index')
            ->with('success', 'پروژه با موفقیت ایجاد شد.');
    }

    public function edit(Project $project)
    {
        return view('projects.edit', $this->formData($project));
    }

    public function update(Request $request, Project $project)
    {
        $request->merge([
            'start_date' => jalaliToGregorianDate($request->input('start_date')),
            'end_date' => jalaliToGregorianDate($request->input('end_date')),
        ]);

        $data = $this->validated($request, $project);
        $data['closed_at'] = ($data['status'] ?? $project->status) === 'closed' && ! $project->closed_at ? now() : $project->closed_at;

        $project->update($data);

        return redirect()->route('projects.index')
            ->with('success', 'پروژه با موفقیت ویرایش شد.');
    }

    public function destroy(Project $project, ProjectDeletionService $deletion)
    {
        $deletion->delete($project);

        return redirect()->route('projects.index')
            ->with('success', 'پروژه و سندهای وابسته با موفقیت حذف شدند.');
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'project_number' => 'nullable|string|max:50|unique:projects,project_number' . ($project ? ',' . $project->id : ''),
            'name' => 'required|string|max:255',
            'party_id' => 'nullable|exists:parties,id',
            'project_manager_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'status' => 'nullable|in:planning,active,procurement,manufacturing,testing,delivered,closed,inactive',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'budget' => 'nullable|numeric|min:0',
            'cost_center_code' => 'nullable|string|max:100',
        ]);
    }

    private function formData(Project $project): array
    {
        return [
            'project' => $project,
            'statuses' => Project::STATUSES,
            'parties' => Party::where('is_active', true)->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ];
    }
}
