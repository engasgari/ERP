<x-erp.ui.list-page
    title="مشتریان CRM"
    description="فهرست مشتریان با پروفایل CRM و وضعیت ارتباط."
    route="crm.customers.index"
    :actions="[
        ['label' => 'مشتری جدید', 'url' => route('crm.customers.create'), 'class' => 'erp-action-edit'],
    ]"
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
                    data-customer-row="{{ $party->id }}"
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
                            <x-erp.ui.row-action icon="edit" label="ویرایش" data-customer-edit="{{ $party->id }}" />
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
                    <tr wire:key="crm-customer-{{ $party->id }}" data-customer-row="{{ $party->id }}">
                        <td class="font-semibold" data-customer-name>{{ $party->name }}</td>
                        <td class="text-nowrap" dir="ltr" data-customer-code>{{ $party->code ?: '-' }}</td>
                        <td class="text-nowrap" dir="ltr" data-customer-mobile>{{ $party->mobile ?: '-' }}</td>
                        <td data-customer-status>
                            <x-erp.ui.status-badge
                                :label="$party->crmProfile?->status_label ?? '-'"
                                tone="info"
                            />
                        </td>
                        <td data-customer-assigned>{{ $party->crmProfile?->assignedUser?->name ?? '-' }}</td>
                        <td>
                            <x-erp.ui.row-actions>
                                <x-erp.ui.row-action icon="view" label="جزئیات" :href="route('crm.customers.show', $party->id)" />
                                <x-erp.ui.row-action icon="edit" label="ویرایش" data-customer-edit="{{ $party->id }}" />
                            </x-erp.ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </x-erp.ui.data-table>
        </x-slot:table>
    </x-crm.ui.responsive-list>

    <div>{{ $items->links() }}</div>

    <div id="customer-edit-modal" class="erp-modal item-edit-modal" hidden>
        <div class="erp-ui-modal-backdrop" data-customer-edit-close></div>
        <div class="erp-ui-modal-panel item-edit-modal-panel">
            <div class="erp-modal-header">
                <h3 id="customer-edit-modal-title">ویرایش مشتری</h3>
                <button type="button" class="erp-modal-close" data-customer-edit-close aria-label="بستن">×</button>
            </div>
            <div class="erp-modal-body item-edit-modal-body">
                <iframe id="customer-edit-iframe" title="ویرایش مشتری" class="item-edit-iframe"></iframe>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const editModal = document.getElementById('customer-edit-modal');
        const editIframe = document.getElementById('customer-edit-iframe');
        const editTitle = document.getElementById('customer-edit-modal-title');
        const customerEditUrlBase = @json(url('crm/customers')) + '/';

        function openCustomerEdit(partyId) {
            if (!editModal || !editIframe || !partyId) {
                return;
            }

            editIframe.src = `${customerEditUrlBase}${partyId}/edit?embedded=1`;
            if (editTitle) {
                editTitle.textContent = 'ویرایش مشتری';
            }
            editModal.hidden = false;
            document.body.classList.add('erp-modal-open');
        }

        function closeCustomerEdit() {
            if (!editModal || !editIframe) {
                return;
            }

            editModal.hidden = true;
            editIframe.src = 'about:blank';
            document.body.classList.remove('erp-modal-open');
        }

        function showCustomerFlash(message, tone = 'success') {
            if (typeof window.showErpToast === 'function') {
                window.showErpToast(message, tone);
            }
        }

        function updateCustomerListRow(party) {
            document.querySelectorAll(`[data-customer-row="${party.id}"]`).forEach((row) => {
                const nameEl = row.querySelector('[data-customer-name]') || row.querySelector('.crm-list-card__title');
                if (nameEl && party.name) {
                    nameEl.textContent = party.name;
                }

                const codeEl = row.querySelector('[data-customer-code]');
                if (codeEl) {
                    codeEl.textContent = party.code || '-';
                }

                const mobileEl = row.querySelector('[data-customer-mobile]');
                if (mobileEl) {
                    mobileEl.textContent = party.mobile || '-';
                }

                const statusEl = row.querySelector('[data-customer-status] span') || row.querySelector('[data-customer-status]');
                if (statusEl && party.status_label) {
                    statusEl.textContent = party.status_label;
                }

                const assignedEl = row.querySelector('[data-customer-assigned]');
                if (assignedEl) {
                    assignedEl.textContent = party.assigned_user_name || '-';
                }
            });
        }

        document.addEventListener('click', (event) => {
            const editTrigger = event.target.closest('[data-customer-edit]');
            if (editTrigger) {
                event.preventDefault();
                openCustomerEdit(editTrigger.getAttribute('data-customer-edit'));
            }

            if (event.target.closest('[data-customer-edit-close]')) {
                closeCustomerEdit();
            }
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin) {
                return;
            }

            const type = event.data?.type;

            if (type === 'party-edit-cancel') {
                closeCustomerEdit();
                return;
            }

            if (type === 'party-validation-error') {
                if (event.data.message) {
                    showCustomerFlash(event.data.message, 'danger');
                }
                return;
            }

            if (type === 'party-saved') {
                if (!event.data.party?.id) {
                    showCustomerFlash(event.data.message || 'ذخیره مشتری تایید نشد.', 'danger');
                    return;
                }

                closeCustomerEdit();
                updateCustomerListRow(event.data.party);
                showCustomerFlash(event.data.message || 'مشتری ویرایش شد.');

                if (typeof Livewire !== 'undefined') {
                    const host = editModal?.closest('[wire\\:id]');
                    const component = host
                        ? Livewire.find(host.getAttribute('wire:id'))
                        : Livewire.first();
                    component?.$refresh?.();
                }
            }
        });
    })();
    </script>
</x-erp.ui.list-page>
