<x-erp.ui.list-page
    title="مشتریان CRM"
    description="فهرست مشتریان با پروفایل CRM و وضعیت ارتباط."
    route="crm.customers.index"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="نام، موبایل، کد">
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        @foreach(\App\Models\Crm\CrmModel::CUSTOMER_STATUSES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-crm.ui.responsive-list :has-items="$items->isNotEmpty()" empty-message="مشتری یافت نشد.">
        <x-slot:cards>
            @foreach($items as $party)
                <x-crm.ui.list-card
                    wire:key="crm-customer-card-{{ $party->id }}"
                    :title="$party->name"
                    :href="route('crm.customers.show', $party->id)"
                    :kicker="$party->code ?: 'بدون کد'"
                    :badge-label="$party->crmProfile?->status_label ?? '-'"
                    badge-tone="info"
                >
                    <x-crm.ui.list-field label="موبایل" emphasis ltr>{{ $party->mobile ?: '-' }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="مسئول">{{ $party->crmProfile?->assignedUser?->name ?? '-' }}</x-crm.ui.list-field>
                    <x-slot:actions>
                        <x-erp.ui.row-actions>
                            <x-erp.ui.row-action icon="view" label="مشاهده پروفایل" :href="route('crm.customers.show', $party->id)" />
                        </x-erp.ui.row-actions>
                    </x-slot:actions>
                </x-crm.ui.list-card>
            @endforeach
        </x-slot:cards>
        <x-slot:table>
            <x-erp.ui.data-table
                :headers="['نام', 'کد', 'موبایل', 'وضعیت CRM', 'مسئول', 'عملیات']"
                empty-message="مشتری یافت نشد."
                :colspan="6"
            >
                @foreach($items as $party)
                    <tr wire:key="crm-customer-{{ $party->id }}">
                        <td class="font-semibold">{{ $party->name }}</td>
                        <td class="text-nowrap" dir="ltr">{{ $party->code ?: '-' }}</td>
                        <td class="text-nowrap" dir="ltr">{{ $party->mobile ?: '-' }}</td>
                        <td>
                            <x-erp.ui.status-badge
                                :label="$party->crmProfile?->status_label ?? '-'"
                                tone="info"
                            />
                        </td>
                        <td>{{ $party->crmProfile?->assignedUser?->name ?? '-' }}</td>
                        <td>
                            <x-erp.ui.row-actions>
                                <x-erp.ui.row-action icon="view" label="جزئیات" :href="route('crm.customers.show', $party->id)" />
                            </x-erp.ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </x-erp.ui.data-table>
        </x-slot:table>
    </x-crm.ui.responsive-list>

    <div>{{ $items->links() }}</div>
</x-erp.ui.list-page>
