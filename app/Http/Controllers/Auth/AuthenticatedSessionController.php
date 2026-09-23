<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AppAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $app = $this->resolveApp($request);

        if ($request->user()) {
            if (session('active_app') === $app) {
                return redirect(app(AppAccessService::class)->homeRouteForApp($app));
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return view('auth.login', [
            'app' => $app,
            'appLabel' => $app === AppAccessService::APP_CRM ? 'CRM' : 'ERP',
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $app = $this->resolveApp($request);

        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $access = app(AppAccessService::class);

        if (! $access->canAccessApp($user, $app)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'شما به این برنامه دسترسی ندارید.',
            ]);
        }

        session(['active_app' => $app]);

        return redirect()->intended($access->homeRouteForApp($app));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $app = session('active_app', AppAccessService::APP_ERP);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect(app(AppAccessService::class)->loginRouteForApp($app));
    }

    private function resolveApp(Request $request): string
    {
        $app = $request->route('app') ?? $request->input('app', AppAccessService::APP_ERP);

        if (! in_array($app, [AppAccessService::APP_ERP, AppAccessService::APP_CRM], true)) {
            abort(404);
        }

        return $app;
    }
}
