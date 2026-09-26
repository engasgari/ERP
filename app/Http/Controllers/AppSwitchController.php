<?php

namespace App\Http\Controllers;

use App\Services\AppAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppSwitchController extends Controller
{
    public function __invoke(Request $request, string $app, AppAccessService $access): RedirectResponse
    {
        if (! in_array($app, [AppAccessService::APP_ERP, AppAccessService::APP_CRM], true)) {
            abort(404);
        }

        if ($request->user() && session('active_app') === $app) {
            return redirect($access->homeRouteForApp($app));
        }

        if ($request->user()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect($access->loginRouteForApp($app));
    }
}
