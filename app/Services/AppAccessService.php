<?php



namespace App\Services;



use App\Models\User;



class AppAccessService

{

    public const APP_ERP = 'erp';



    public const APP_CRM = 'crm';



    public function canAccessErp(User $user): bool

    {

        if ($user->isAdmin()) {

            return true;

        }



        $erpPermissions = [

            'base-info.view', 'commerce.view', 'accounting.view', 'financial.view',

            'inventory.view', 'projects.view', 'employees.view', 'reports.view',

            'hr.view', 'salaries.view', 'treasury.manage',

        ];



        foreach ($erpPermissions as $permission) {

            if ($user->hasPermission($permission)) {

                return true;

            }

        }



        return false;

    }



    public function canAccessCrm(User $user): bool

    {

        return $user->isAdmin() || $user->hasPermission('crm.dashboard.view');

    }



    public function canAccessApp(User $user, string $app): bool

    {

        return match ($app) {

            self::APP_ERP => $this->canAccessErp($user),

            self::APP_CRM => $this->canAccessCrm($user),

            default => false,

        };

    }



    public function homeRouteForApp(string $app): string

    {

        return $app === self::APP_CRM ? route('crm.dashboard') : route('dashboard');

    }



    public function homeRouteForSession(): string

    {

        return $this->homeRouteForApp(session('active_app', self::APP_ERP));

    }



    public function loginRouteForApp(string $app): string

    {

        return $app === self::APP_CRM ? route('crm.login') : route('login');

    }

}

