<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function employeeAccesses(): HasMany
    {
        return $this->hasMany(UserEmployeeAccess::class);
    }

    public function accessibleEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'user_employee_access')
            ->withPivot([
                'can_view_work_logs',
                'can_manage_work_logs',
                'can_view_salaries',
                'can_manage_salaries',
            ])
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->roles()->where('name', 'admin')->exists();
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('key', $key))
            ->exists();
    }

    public function canAccessEmployee(int $employeeId, string $scope): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $column = match ($scope) {
            'worklogs.manage' => 'can_manage_work_logs',
            'worklogs.view' => 'can_view_work_logs',
            'salaries.manage' => 'can_manage_salaries',
            'salaries.view' => 'can_view_salaries',
            default => null,
        };

        if (!$column) {
            return false;
        }

        return $this->employeeAccesses()
            ->where('employee_id', $employeeId)
            ->where($column, true)
            ->exists();
    }

    public function accessibleEmployeeIds(string $scope): array
    {
        if ($this->isAdmin()) {
            return Employee::pluck('id')->all();
        }

        $column = match ($scope) {
            'worklogs.manage' => 'can_manage_work_logs',
            'worklogs.view' => 'can_view_work_logs',
            'salaries.manage' => 'can_manage_salaries',
            'salaries.view' => 'can_view_salaries',
            default => null,
        };

        if (!$column) {
            return [];
        }

        return $this->employeeAccesses()
            ->where($column, true)
            ->pluck('employee_id')
            ->all();
    }
}
