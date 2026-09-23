<x-guest-layout portal>
    <div class="guest-portal__main guest-portal__main--login">
        <div class="guest-portal__brand guest-portal__brand--compact">
            <div class="guest-portal__logo-shell guest-portal__logo-shell--sm">
                <img
                    src="{{ asset('logo-aale.png') }}"
                    alt="لوگوی بیکران پایش آله"
                    class="guest-portal__logo"
                    width="72"
                    height="72"
                >
            </div>
            <h1 class="guest-portal__company guest-portal__company--sm">بیکران پایش آله</h1>
            <p class="guest-portal__date">{{ todayJalaliDate() }}</p>
        </div>

        <section class="guest-login-card">
            <header class="guest-login-card__head">
                <h2 class="guest-login-card__title">ورود به {{ $appLabel }}</h2>
            </header>

            <x-auth-session-status class="guest-login-card__status" :status="session('status')" />

            <form
                method="POST"
                action="{{ $app === 'crm' ? route('crm.login.store') : route('login') }}"
                class="guest-login-card__form"
            >
                @csrf
                <input type="hidden" name="app" value="{{ $app }}">

                <label class="guest-login-field">
                    <span class="guest-login-field__label">ایمیل</span>
                    <input
                        id="email"
                        class="guest-login-field__input"
                        dir="ltr"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="example@aale.ir"
                    >
                    <x-input-error :messages="$errors->get('email')" class="guest-login-field__error" />
                </label>

                <label class="guest-login-field">
                    <span class="guest-login-field__label">رمز عبور</span>
                    <input
                        id="password"
                        class="guest-login-field__input"
                        dir="ltr"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="رمز عبور"
                    >
                    <x-input-error :messages="$errors->get('password')" class="guest-login-field__error" />
                </label>

                <div class="guest-login-card__row">
                    <label for="remember_me" class="guest-login-remember">
                        <input
                            id="remember_me"
                            type="checkbox"
                            class="guest-login-remember__input"
                            name="remember"
                        >
                        <span>مرا به خاطر بسپار</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="guest-login-link" href="{{ route('password.request') }}">
                            فراموشی رمز
                        </a>
                    @endif
                </div>

                <button type="submit" class="guest-login-submit">
                    ورود
                </button>
            </form>

            <div class="guest-login-card__foot">
                <a href="{{ route('home') }}" class="guest-login-link">بازگشت به انتخاب برنامه</a>
            </div>
        </section>
    </div>

    <footer class="guest-portal__footer">
        <p>
            طراحی و پیاده‌سازی توسط شرکت بیکران پایش آله انجام شده و تمامی حقوق برنامه‌ها برای این شرکت محفوظ است.
            <span class="guest-portal__footer-year">سال ۱۴۰۵</span>
        </p>
    </footer>
</x-guest-layout>
