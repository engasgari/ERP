<x-guest-layout>
    <div class="mb-5 text-right">
        <h1 class="text-lg font-bold text-slate-800 opacity-80">ورود به سیستم ERP</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 opacity-70">برای ادامه، اطلاعات حساب کاربری خود را وارد کنید.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="ایمیل" class="mb-1 text-right font-bold text-slate-700" />
            <x-text-input id="email"
                          class="block w-full text-left"
                          dir="ltr"
                          type="email"
                          name="email"
                          :value="old('email')"
                          required
                          autofocus
                          autocomplete="username"
                          placeholder="example@aale.ir" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="رمز عبور" class="mb-1 text-right font-bold text-slate-700" />
            <x-text-input id="password"
                          class="block w-full text-left"
                          dir="ltr"
                          type="password"
                          name="password"
                          required
                          autocomplete="current-password"
                          placeholder="رمز عبور" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                <input id="remember_me"
                       type="checkbox"
                       class="rounded border-slate-300 text-slate-700 shadow-sm focus:ring-slate-500"
                       name="remember">
                <span>مرا به خاطر بسپار</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-bold text-slate-600 underline-offset-4 hover:text-slate-900 hover:underline"
                   href="{{ route('password.request') }}">
                    فراموشی رمز عبور
                </a>
            @endif
        </div>

        <button type="submit"
                class="mt-2 inline-flex items-center justify-center rounded-md bg-slate-100 px-5 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">
            ورود
        </button>
    </form>
</x-guest-layout>
