<x-erp.ui.list-page
    title="پایپ‌لاین فروش"
    description="فرصت‌ها را بین مراحل جابه‌جا کنید."
    route="crm.pipeline.index"
    panelClass="crm-pipeline-page"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field">پایپ‌لاین
                    <select wire:model.live="pipeline_id">
                        @foreach($pipelines as $pipe)
                            <option value="{{ $pipe->id }}">{{ $pipe->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="عنوان، شماره">
                </label>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    @if($pipeline)
        <div class="crm-kanban-shell" x-data="{ dragging: null }">
            <div class="crm-kanban-board">
                <div class="crm-kanban-columns">
                    @foreach($stages as $stage)
                        @php $cards = $grouped->get((int) $stage->id, collect()); @endphp
                        <div
                            class="crm-kanban-column"
                            wire:key="kanban-stage-{{ $stage->id }}"
                            x-on:dragover.prevent
                            x-on:drop.prevent="$wire.dropOnStage({{ $stage->id }})"
                        >
                            <div class="crm-kanban-column-header">
                                <span class="truncate">{{ $stage->name }}</span>
                                <span class="crm-kanban-column-count">{{ $cards->count() }}</span>
                            </div>
                            <div class="crm-kanban-column-body">
                                @forelse($cards as $opp)
                                    <div wire:key="kanban-{{ $opp->id }}" class="crm-kanban-card">
                                        <div class="crm-kanban-card-top">
                                            <div
                                                class="crm-kanban-drag-handle"
                                                draggable="true"
                                                title="جابه‌جایی"
                                                x-on:dragstart="dragging = {{ $opp->id }}; $wire.startDrag({{ $opp->id }})"
                                                x-on:dragend="dragging = null"
                                            >
                                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="7" cy="5" r="1.2"/><circle cx="13" cy="5" r="1.2"/><circle cx="7" cy="10" r="1.2"/><circle cx="13" cy="10" r="1.2"/><circle cx="7" cy="15" r="1.2"/><circle cx="13" cy="15" r="1.2"/></svg>
                                            </div>
                                            <button
                                                type="button"
                                                class="crm-kanban-card-summary"
                                                wire:click="openOpportunityTimeline({{ $opp->id }})"
                                            >
                                                <span class="crm-kanban-card-title">{{ $opp->title }}</span>
                                                <span class="crm-kanban-card-party">{{ $opp->party?->name ?? '-' }}</span>
                                            </button>
                                        </div>
                                        <div class="crm-kanban-card-meta">
                                            <span class="crm-kanban-card-amount">{{ formatMoney((float) $opp->amount) }}</span>
                                            @if($opp->open_tasks_count > 0)
                                                <span class="crm-kanban-card-badge">{{ $opp->open_tasks_count }} وظیفه</span>
                                            @endif
                                        </div>
                                        @if($opp->invoice)
                                            <div class="crm-kanban-card-proforma">
                                                <button type="button" data-crm-proforma-show="{{ $opp->invoice->id }}">
                                                    پیش‌فاکتور {{ $opp->invoice->number }}
                                                </button>
                                            </div>
                                        @endif
                                        <div class="crm-kanban-card-actions" @click.stop @mousedown.stop>
                                            @php
                                                $logCallQuery = array_filter([
                                                    'pipeline_id' => $pipeline_id !== '' ? $pipeline_id : null,
                                                    'search' => $search !== '' ? $search : null,
                                                ]);
                                                $logCallUrl = route('crm.pipeline.log-call', $opp).($logCallQuery ? '?'.http_build_query($logCallQuery) : '');
                                            @endphp
                                            <x-erp.ui.row-action
                                                icon="phone"
                                                label="ثبت تماس"
                                                :href="$logCallUrl"
                                                wire:navigate
                                                class="crm-kanban-icon-btn crm-kanban-log-call-mobile"
                                            />
                                            <x-erp.ui.row-action
                                                icon="phone"
                                                label="ثبت تماس"
                                                wire:click="openOpportunityActivity({{ $opp->id }})"
                                                class="crm-kanban-icon-btn crm-kanban-log-call-desktop"
                                            />
                                            <x-erp.ui.row-action
                                                icon="edit"
                                                label="ویرایش"
                                                wire:click="openOpportunityEdit({{ $opp->id }})"
                                                class="crm-kanban-icon-btn"
                                            />
                                            @if($opp->open_tasks_count > 0)
                                                <x-erp.ui.row-action
                                                    icon="confirm"
                                                    tone="success"
                                                    label="تکمیل وظیفه"
                                                    wire:click="completeNextTask({{ $opp->id }})"
                                                    class="crm-kanban-icon-btn"
                                                />
                                            @endif
                                            @if(! $opp->invoice && (int) ($opp->stage?->sort_order ?? 0) >= 5)
                                                <x-erp.ui.row-action
                                                    icon="issue"
                                                    label="صدور پیش‌فاکتور"
                                                    class="crm-kanban-icon-btn"
                                                    onclick="window.openCrmProformaCompose({{ $opp->id }})"
                                                />
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="crm-kanban-empty">فرصت باز در این مرحله نیست</div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <x-erp.ui.empty-state message="پایپ‌لاین فعالی یافت نشد." />
    @endif

    @if($showTimelineModal && $opportunityTimelineOverview)
        <x-erp.ui.details-modal :title="'گزارش فرصت ' . $opportunityTimelineOverview['opportunity']->number">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeOpportunityTimeline">×</button>
            </x-slot>
            <x-slot name="footer">
                @include('livewire.crm.partials.opportunity-timeline-modal-footer', [
                    'opportunityTimelineOverview' => $opportunityTimelineOverview,
                    'proformaButtonMode' => 'kanban',
                ])
            </x-slot>
            @include('livewire.crm.partials.opportunity-timeline-panel', [
                'opportunityTimelineOverview' => $opportunityTimelineOverview,
            ])
        </x-erp.ui.details-modal>
    @endif

    @if($showActivityModal)
        <x-erp.ui.details-modal :title="$editingActivityId ? 'ویرایش تماس / فعالیت' : 'ثبت تماس / فعالیت'">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeActivityModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeActivityModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveActivity">ذخیره</button>
            </x-slot>
            @include('livewire.crm.partials.activity-form', ['typeOptions' => $typeOptions])
        </x-erp.ui.details-modal>
    @endif

    @if($showOpportunityModal)
        <x-erp.ui.details-modal :title="$editingOpportunityId ? 'ویرایش فرصت' : 'فرصت جدید'">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeOpportunityModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeOpportunityModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveOpportunity">ذخیره</button>
            </x-slot>
            @include('livewire.crm.partials.opportunity-form')
        </x-erp.ui.details-modal>
    @endif

    @include('livewire.crm.partials.opportunity-quick-create-modals')

    <div id="crm-proforma-show-modal" class="erp-modal invoice-iframe-modal" hidden>
        <div class="erp-ui-modal-backdrop" data-crm-proforma-show-close></div>
        <div class="erp-ui-modal-panel invoice-iframe-modal-panel">
            <div class="erp-modal-header">
                <h3 id="crm-proforma-show-modal-title">پیش‌فاکتور</h3>
                <button type="button" class="erp-modal-close" data-crm-proforma-show-close aria-label="بستن">×</button>
            </div>
            <div class="erp-modal-body invoice-iframe-modal-body">
                <iframe id="crm-proforma-show-iframe" title="نمایش پیش‌فاکتور" class="invoice-iframe"></iframe>
            </div>
        </div>
    </div>

    <div id="crm-proforma-edit-modal" class="erp-modal invoice-iframe-modal" hidden>
        <div class="erp-ui-modal-backdrop" data-crm-proforma-edit-close></div>
        <div class="erp-ui-modal-panel invoice-iframe-modal-panel">
            <div class="erp-modal-header">
                <h3 id="crm-proforma-edit-modal-title">ویرایش پیش‌فاکتور</h3>
                <button type="button" class="erp-modal-close" data-crm-proforma-edit-close aria-label="بستن">×</button>
            </div>
            <div class="erp-modal-body invoice-iframe-modal-body">
                <iframe id="crm-proforma-edit-iframe" title="ویرایش پیش‌فاکتور" class="invoice-iframe"></iframe>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const proformaUrlBase = @json(url('crm/proforma')) + '/';
        const crmUrlBase = @json(url('crm'));

        function proformaShowModal() {
            return document.getElementById('crm-proforma-show-modal');
        }

        function proformaShowIframe() {
            return document.getElementById('crm-proforma-show-iframe');
        }

        function proformaShowTitle() {
            return document.getElementById('crm-proforma-show-modal-title');
        }

        function proformaEditModal() {
            return document.getElementById('crm-proforma-edit-modal');
        }

        function proformaEditIframe() {
            return document.getElementById('crm-proforma-edit-iframe');
        }

        function proformaEditTitle() {
            return document.getElementById('crm-proforma-edit-modal-title');
        }

        function openProformaCompose(opportunityId) {
            const editModal = proformaEditModal();
            const editIframe = proformaEditIframe();
            const editTitle = proformaEditTitle();

            if (!editModal || !editIframe) {
                return;
            }

            closeProformaShow();
            editIframe.src = `${crmUrlBase}/opportunities/${opportunityId}/proforma/compose`;
            if (editTitle) {
                editTitle.textContent = 'صدور پیش‌فاکتور';
            }
            editModal.hidden = false;
            document.body.classList.add('erp-modal-open');
        }

        function openProformaShow(invoiceId) {
            const showModal = proformaShowModal();
            const showIframe = proformaShowIframe();
            const showTitle = proformaShowTitle();

            if (!showModal || !showIframe) {
                return;
            }

            closeProformaEdit();
            showIframe.src = `${proformaUrlBase}${invoiceId}`;
            if (showTitle) {
                showTitle.textContent = 'پیش‌فاکتور';
            }
            showModal.hidden = false;
            document.body.classList.add('erp-modal-open');
        }

        function closeProformaShow() {
            const showModal = proformaShowModal();
            const showIframe = proformaShowIframe();
            const editModal = proformaEditModal();

            if (!showModal || !showIframe) {
                return;
            }

            showModal.hidden = true;
            showIframe.src = 'about:blank';
            if (!editModal || editModal.hidden) {
                document.body.classList.remove('erp-modal-open');
            }
        }

        function openProformaEdit(invoiceId) {
            const editModal = proformaEditModal();
            const editIframe = proformaEditIframe();
            const editTitle = proformaEditTitle();

            if (!editModal || !editIframe) {
                return;
            }

            closeProformaShow();
            editIframe.src = `${proformaUrlBase}${invoiceId}/edit`;
            if (editTitle) {
                editTitle.textContent = 'ویرایش پیش‌فاکتور';
            }
            editModal.hidden = false;
            document.body.classList.add('erp-modal-open');
        }

        function closeProformaEdit() {
            const editModal = proformaEditModal();
            const editIframe = proformaEditIframe();
            const showModal = proformaShowModal();

            if (!editModal || !editIframe) {
                return;
            }

            editModal.hidden = true;
            editIframe.src = 'about:blank';
            if (!showModal || showModal.hidden) {
                document.body.classList.remove('erp-modal-open');
            }
        }

        window.openCrmProformaCompose = openProformaCompose;

        document.addEventListener('click', (event) => {
            const showTrigger = event.target.closest('[data-crm-proforma-show]');
            if (showTrigger) {
                event.preventDefault();
                openProformaShow(showTrigger.dataset.crmProformaShow);
            }

            const editTrigger = event.target.closest('[data-crm-proforma-edit]');
            if (editTrigger) {
                event.preventDefault();
                openProformaEdit(editTrigger.dataset.crmProformaEdit);
            }

            if (event.target.closest('[data-crm-proforma-show-close]')) {
                closeProformaShow();
            }

            if (event.target.closest('[data-crm-proforma-edit-close]')) {
                closeProformaEdit();
            }
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin) {
                return;
            }

            const type = event.data?.type;

            if (type === 'invoice-show-cancel') {
                closeProformaShow();
                return;
            }

            if (type === 'invoice-edit-cancel') {
                closeProformaEdit();
                return;
            }

            if (type === 'invoice-open-edit') {
                closeProformaShow();
                if (event.data.invoiceId) {
                    openProformaEdit(event.data.invoiceId);
                }
                return;
            }

            if (type === 'invoice-saved') {
                closeProformaEdit();
                if (event.data.message && typeof window.showErpToast === 'function') {
                    window.showErpToast(event.data.message);
                }
                if (typeof Livewire !== 'undefined' && Livewire.first()) {
                    Livewire.first().$refresh();
                }
                return;
            }

            if (type === 'invoice-updated') {
                const showIframe = proformaShowIframe();
                const showModal = proformaShowModal();

                if (showIframe && showModal && !showModal.hidden) {
                    const reloadId = event.data.reloadShowId || event.data.invoice?.id;
                    if (reloadId) {
                        showIframe.src = `${proformaUrlBase}${reloadId}`;
                    }
                }
                if (event.data.message && typeof window.showErpToast === 'function') {
                    window.showErpToast(event.data.message);
                }
            }
        });
    })();
    </script>
</x-erp.ui.list-page>
