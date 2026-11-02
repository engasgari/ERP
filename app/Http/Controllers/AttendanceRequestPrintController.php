<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLeave;
use App\Models\AttendanceMission;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AttendanceRequestPrintController extends Controller
{
    public function leave(Request $request, AttendanceLeave $leave): View
    {
        $this->authorizeRequestPrint($leave->employee_id, $leave->requested_by);

        $leave->loadMissing([
            'employee.party',
            'employee.organizationUnit',
            'employee.positionRecord',
            'requestedBy',
            'approvedBy',
        ]);

        return view('attendance.leave-request-print', [
            'leave' => $leave,
            'employee' => $leave->employee,
            'company' => CompanySetting::query()->first(),
            'backUrl' => $this->backUrl($request, 'attendance.leaves'),
        ]);
    }

    public function mission(Request $request, AttendanceMission $mission): View
    {
        $this->authorizeRequestPrint($mission->employee_id, $mission->requested_by);

        $mission->loadMissing([
            'employee.party',
            'employee.organizationUnit',
            'employee.positionRecord',
            'project',
            'costCenter',
            'requestedBy',
            'approvedBy',
        ]);

        return view('attendance.mission-request-print', [
            'mission' => $mission,
            'employee' => $mission->employee,
            'company' => CompanySetting::query()->first(),
            'backUrl' => $this->backUrl($request, 'attendance.missions'),
        ]);
    }

    private function authorizeRequestPrint(?int $employeeId, ?int $requestedBy): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        abort_unless($user, 403);

        if ($user->isAdmin() || $user->hasPermission('attendance.view')) {
            return;
        }

        if ($requestedBy && (int) $requestedBy === (int) $user->id) {
            return;
        }

        if ($employeeId && $this->userLinkedEmployeeId($user) === (int) $employeeId) {
            return;
        }

        abort(403);
    }

    private function userLinkedEmployeeId(User $user): ?int
    {
        $accessible = $user->accessibleEmployees()->first();
        if ($accessible) {
            return (int) $accessible->id;
        }

        $employee = Employee::query()
            ->where(function ($query) use ($user): void {
                if (Schema::hasColumn('employees', 'user_id')) {
                    $query->where('user_id', $user->id)
                        ->orWhere('email', $user->email)
                        ->orWhereHas('party', fn ($party) => $party->where('email', $user->email));

                    return;
                }

                $query->where('email', $user->email)
                    ->orWhereHas('party', fn ($party) => $party->where('email', $user->email));
            })
            ->first();

        return $employee?->id ? (int) $employee->id : null;
    }

    private function backUrl(Request $request, string $defaultRoute): string
    {
        $back = trim((string) $request->query('back', ''));

        if ($back !== '' && str_starts_with($back, '/') && ! str_starts_with($back, '//')) {
            return $back;
        }

        return route($defaultRoute);
    }
}
