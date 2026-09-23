<x-erp.ui.list-page

    title="ثبت تماس"

    :description="'فرصت '.$opportunity->number.' — '.$opportunity->title"

    route="crm.pipeline.index"

    panelClass="crm-log-call-page"

>

    <div class="crm-log-call-back">

        <button type="button" class="erp-action-btn" wire:click="cancel">بازگشت به خط فروش</button>

    </div>



    <div

        class="crm-log-call-shell"

        x-data="{ callHint: false }"

        x-init="callHint = sessionStorage.getItem('crm-log-call-{{ $opportunity->id }}') === '1'"

    >

        <section class="crm-log-call-hero" aria-label="برقراری تماس">

            <p class="crm-log-call-hero__party">{{ $opportunity->party?->name ?? 'بدون مشتری' }}</p>

            @if($dialPhone)

                <a

                    href="{{ $dialPhone['tel'] }}"

                    class="crm-log-call-dial-btn"

                    @click="sessionStorage.setItem('crm-log-call-{{ $opportunity->id }}', '1'); callHint = true"

                >

                    <span class="crm-log-call-dial-btn__icon" aria-hidden="true">

                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>

                    </span>

                    <span class="crm-log-call-dial-btn__text">برقراری تماس</span>

                    <span class="crm-log-call-dial-btn__number" dir="ltr">{{ $dialPhone['display'] }}</span>

                    <span class="crm-log-call-dial-btn__meta">{{ $dialPhone['source'] }}</span>

                </a>

            @else

                <div class="crm-log-call-no-phone">

                    شماره تماس برای این فرصت ثبت نشده است. پس از تماس، فقط نتیجه را در فرم پایین ثبت کنید.

                </div>

            @endif

            <p class="crm-log-call-hero__hint">

                ابتدا تماس را از گوشی برقرار کنید؛ بعد از پایان تماس به همین صفحه برگردید و فرم را تکمیل کنید.

            </p>

            <p class="crm-log-call-hero__return" x-show="callHint" x-cloak>

                خوش آمدید — نتیجه تماس را در فرم زیر ثبت کنید.

            </p>

        </section>



        <section class="crm-log-call-form" aria-label="ثبت نتیجه تماس">

            <h2 class="crm-log-call-form__title">نتیجه تماس</h2>

            @include('livewire.crm.partials.activity-form', ['typeOptions' => $typeOptions, 'fixedType' => 'call'])

            <div class="crm-log-call-form__actions">

                <button type="button" class="erp-action-btn" wire:click="cancel">انصراف</button>

                <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveActivity">ذخیره فعالیت</button>

            </div>

        </section>

    </div>

</x-erp.ui.list-page>

