<x-erp.ui.list-page

    title="جستجوی CRM"

    description="جستجو در مشتریان، سرنخ‌ها و فرصت‌ها."

    route="crm.search.index"

>

    <x-slot name="filters">

        <x-erp.ui.filter-bar wire:submit.prevent>

            <div class="erp-filter-row">

                <label class="erp-filter-field md:col-span-3">عبارت جستجو

                    <input wire:model.live.debounce.400ms="query" placeholder="حداقل ۲ کاراکتر وارد کنید">

                </label>

            </div>

        </x-erp.ui.filter-bar>

    </x-slot>



    @if(strlen(trim($query)) < 2)

        <x-erp.ui.empty-state message="برای جستجو حداقل ۲ کاراکتر وارد کنید." />

    @else

        <div class="space-y-6">

            <div>

                <h3 class="font-semibold mb-2">مشتریان ({{ $results['customers']->count() }})</h3>

                <x-crm.ui.responsive-list :has-items="$results['customers']->isNotEmpty()" empty-message="موردی یافت نشد.">

                    <x-slot:cards>

                        @foreach($results['customers'] as $party)

                            <x-crm.ui.list-card

                                wire:key="search-customer-card-{{ $party->id }}"

                                :title="$party->name"

                                :href="route('crm.customers.show', $party->id)"

                                :kicker="$party->code ?: 'بدون کد'"

                            >

                                <x-crm.ui.list-field label="موبایل" emphasis ltr>{{ $party->mobile ?: '-' }}</x-crm.ui.list-field>

                            </x-crm.ui.list-card>

                        @endforeach

                    </x-slot:cards>

                    <x-slot:table>

                        <x-erp.ui.data-table :headers="['نام', 'کد', 'موبایل', 'عملیات']" empty-message="موردی یافت نشد." :colspan="4">

                            @foreach($results['customers'] as $party)

                                <tr wire:key="search-customer-{{ $party->id }}">

                                    <td>{{ $party->name }}</td>

                                    <td dir="ltr">{{ $party->code ?: '-' }}</td>

                                    <td dir="ltr">{{ $party->mobile ?: '-' }}</td>

                                    <td><a href="{{ route('crm.customers.show', $party->id) }}" class="text-blue-700 text-sm">مشاهده</a></td>

                                </tr>

                            @endforeach

                        </x-erp.ui.data-table>

                    </x-slot:table>

                </x-crm.ui.responsive-list>

            </div>



            <div>

                <h3 class="font-semibold mb-2">سرنخ‌ها ({{ $results['leads']->count() }})</h3>

                <x-crm.ui.responsive-list :has-items="$results['leads']->isNotEmpty()" empty-message="موردی یافت نشد.">

                    <x-slot:cards>

                        @foreach($results['leads'] as $lead)

                            <x-crm.ui.list-card

                                wire:key="search-lead-card-{{ $lead->id }}"

                                :title="$lead->title"

                                :href="route('crm.leads.show', $lead->id)"

                                :kicker="$lead->number"

                                :badge-label="$lead->status_label"

                                badge-tone="info"

                            />

                        @endforeach

                    </x-slot:cards>

                    <x-slot:table>

                        <x-erp.ui.data-table :headers="['شماره', 'عنوان', 'وضعیت', 'عملیات']" empty-message="موردی یافت نشد." :colspan="4">

                            @foreach($results['leads'] as $lead)

                                <tr wire:key="search-lead-{{ $lead->id }}">

                                    <td>{{ $lead->number }}</td>

                                    <td>{{ $lead->title }}</td>

                                    <td>{{ $lead->status_label }}</td>

                                    <td><a href="{{ route('crm.leads.show', $lead->id) }}" class="text-blue-700 text-sm">مشاهده</a></td>

                                </tr>

                            @endforeach

                        </x-erp.ui.data-table>

                    </x-slot:table>

                </x-crm.ui.responsive-list>

            </div>



            <div>

                <h3 class="font-semibold mb-2">فرصت‌ها ({{ $results['opportunities']->count() }})</h3>

                <x-crm.ui.responsive-list :has-items="$results['opportunities']->isNotEmpty()" empty-message="موردی یافت نشد.">

                    <x-slot:cards>

                        @foreach($results['opportunities'] as $opp)

                            <x-crm.ui.list-card

                                wire:key="search-opp-card-{{ $opp->id }}"

                                :title="$opp->title"

                                :kicker="$opp->number"

                            >

                                <x-crm.ui.list-field label="مشتری">{{ $opp->party?->name ?? '-' }}</x-crm.ui.list-field>

                                <x-crm.ui.list-field label="مبلغ" emphasis>{{ formatMoney((float) $opp->amount) }}</x-crm.ui.list-field>

                            </x-crm.ui.list-card>

                        @endforeach

                    </x-slot:cards>

                    <x-slot:table>

                        <x-erp.ui.data-table :headers="['شماره', 'عنوان', 'مشتری', 'مبلغ']" empty-message="موردی یافت نشد." :colspan="4">

                            @foreach($results['opportunities'] as $opp)

                                <tr wire:key="search-opp-{{ $opp->id }}">

                                    <td>{{ $opp->number }}</td>

                                    <td>{{ $opp->title }}</td>

                                    <td>{{ $opp->party?->name ?? '-' }}</td>

                                    <td>{{ formatMoney((float) $opp->amount) }}</td>

                                </tr>

                            @endforeach

                        </x-erp.ui.data-table>

                    </x-slot:table>

                </x-crm.ui.responsive-list>

            </div>

        </div>

    @endif

</x-erp.ui.list-page>

