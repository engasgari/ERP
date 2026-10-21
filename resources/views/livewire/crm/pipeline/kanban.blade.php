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
        <script>
            window.crmKanbanBoard = function ($wire) {
                return {
                    draggingId: null,
                    pointerId: null,
                    startX: 0,
                    startY: 0,
                    moved: false,
                    cardEl: null,
                    ghostEl: null,
                    activeStageId: null,
                    grabOffsetX: 0,
                    grabOffsetY: 0,
                    threshold: 10,
                    _onMove: null,
                    _onUp: null,

                    beginDrag(event, opportunityId) {
                        if (event.pointerType === 'mouse' && event.button !== 0) {
                            return;
                        }

                        const handle = event.currentTarget;
                        const card = handle.closest('.crm-kanban-card');
                        if (!card) {
                            return;
                        }

                        event.preventDefault();
                        event.stopPropagation();

                        this.cleanupGhost();
                        this.clearColumnHighlights();

                        this.draggingId = opportunityId;
                        this.pointerId = event.pointerId;
                        this.startX = event.clientX;
                        this.startY = event.clientY;
                        this.moved = false;
                        this.cardEl = card;
                        this.activeStageId = null;

                        const rect = card.getBoundingClientRect();
                        this.grabOffsetX = event.clientX - rect.left;
                        this.grabOffsetY = event.clientY - rect.top;

                        try {
                            handle.setPointerCapture(event.pointerId);
                        } catch (_) {
                            // ignore
                        }

                        $wire.startDrag(opportunityId);

                        this._onMove = (e) => this.onPointerMove(e);
                        this._onUp = (e) => this.onPointerUp(e);
                        window.addEventListener('pointermove', this._onMove, { passive: false });
                        window.addEventListener('pointerup', this._onUp);
                        window.addEventListener('pointercancel', this._onUp);
                    },

                    onPointerMove(event) {
                        if (this.pointerId !== null && event.pointerId !== this.pointerId) {
                            return;
                        }
                        if (!this.draggingId) {
                            return;
                        }

                        const dx = event.clientX - this.startX;
                        const dy = event.clientY - this.startY;

                        if (!this.moved && Math.hypot(dx, dy) < this.threshold) {
                            return;
                        }

                        if (!this.moved) {
                            this.moved = true;
                            this.createGhost();
                            this.cardEl?.classList.add('is-dragging');
                            document.body.classList.add('crm-kanban-dragging');
                        }

                        event.preventDefault();
                        this.positionGhost(event.clientX, event.clientY);
                        this.highlightColumnAt(event.clientX, event.clientY);
                    },

                    onPointerUp(event) {
                        if (this.pointerId !== null && event.pointerId !== this.pointerId) {
                            return;
                        }

                        const stageId = this.activeStageId;
                        const didMove = this.moved;
                        const opportunityId = this.draggingId;

                        this.teardownListeners();
                        this.cleanupGhost();
                        this.clearColumnHighlights();
                        this.cardEl?.classList.remove('is-dragging');
                        document.body.classList.remove('crm-kanban-dragging');

                        this.draggingId = null;
                        this.pointerId = null;
                        this.cardEl = null;
                        this.activeStageId = null;
                        this.moved = false;

                        if (didMove && stageId) {
                            $wire.dropOnStage(stageId);
                            return;
                        }

                        if (opportunityId) {
                            $wire.cancelDrag();
                        }
                    },

                    createGhost() {
                        if (!this.cardEl || this.ghostEl) {
                            return;
                        }

                        const ghost = this.cardEl.cloneNode(true);
                        ghost.classList.add('crm-kanban-drag-ghost');
                        ghost.querySelectorAll('button, a, input, select, textarea').forEach((el) => {
                            el.setAttribute('disabled', 'disabled');
                            el.removeAttribute('href');
                            el.removeAttribute('wire:click');
                            el.onclick = null;
                        });

                        const rect = this.cardEl.getBoundingClientRect();
                        ghost.style.width = `${rect.width}px`;
                        document.body.appendChild(ghost);
                        this.ghostEl = ghost;
                        this.positionGhost(this.startX, this.startY);
                    },

                    positionGhost(clientX, clientY) {
                        if (!this.ghostEl) {
                            return;
                        }

                        this.ghostEl.style.transform = `translate3d(${clientX - this.grabOffsetX}px, ${clientY - this.grabOffsetY}px, 0)`;
                    },

                    highlightColumnAt(clientX, clientY) {
                        this.clearColumnHighlights();

                        const target = document.elementFromPoint(clientX, clientY);
                        const column = target?.closest?.('.crm-kanban-column');
                        if (!column) {
                            this.activeStageId = null;
                            return;
                        }

                        column.classList.add('is-drop-target');
                        this.activeStageId = Number(column.dataset.stageId || 0) || null;
                    },

                    clearColumnHighlights() {
                        document.querySelectorAll('.crm-kanban-column.is-drop-target').forEach((el) => {
                            el.classList.remove('is-drop-target');
                        });
                    },

                    cleanupGhost() {
                        this.ghostEl?.remove();
                        this.ghostEl = null;
                    },

                    teardownListeners() {
                        if (this._onMove) {
                            window.removeEventListener('pointermove', this._onMove);
                        }
                        if (this._onUp) {
                            window.removeEventListener('pointerup', this._onUp);
                            window.removeEventListener('pointercancel', this._onUp);
                        }
                        this._onMove = null;
                        this._onUp = null;
                    },
                };
            };
        </script>
        <div class="crm-kanban-shell" x-data="crmKanbanBoard($wire)">
            <div class="crm-kanban-board">
                <div class="crm-kanban-columns">
                    @foreach($stages as $stage)
                        @php $cards = $grouped->get((int) $stage->id, collect()); @endphp
                        <div
                            class="crm-kanban-column"
                            data-stage-id="{{ $stage->id }}"
                            wire:key="kanban-stage-{{ $stage->id }}"
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
                                                title="برای جابه‌جایی نگه دارید و بکشید"
                                                role="button"
                                                tabindex="0"
                                                aria-label="جابه‌جایی فرصت"
                                                x-on:pointerdown="beginDrag($event, {{ $opp->id }})"
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
                                                @if($opp->invoice->document_type === 'invoice')
                                                    <button
                                                        type="button"
                                                        data-invoice-show="{{ $opp->invoice->id }}"
                                                        data-invoice-show-crm="1"
                                                        data-invoice-show-title="فاکتور فروش {{ $opp->invoice->number }}"
                                                    >
                                                        فاکتور فروش {{ $opp->invoice->number }}
                                                    </button>
                                                @else
                                                    <button type="button" data-crm-proforma-show="{{ $opp->invoice->id }}">
                                                        پیش‌فاکتور {{ $opp->invoice->number }}
                                                        @if($opp->invoice->status === 'cancelled')
                                                            (ابطال‌شده)
                                                        @endif
                                                    </button>
                                                @endif
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
                                            @if(! $opp->invoice && (int) ($opp->stage?->sort_order ?? 0) >= 5 && $opp->status === 'open')
                                                <x-erp.ui.row-action
                                                    icon="issue"
                                                    label="صدور پیش‌فاکتور"
                                                    class="crm-kanban-icon-btn"
                                                    onclick="window.openCrmProformaCompose({{ $opp->id }})"
                                                />
                                                <x-erp.ui.row-action
                                                    icon="view"
                                                    label="اتصال پیش‌فاکتور موجود"
                                                    class="crm-kanban-icon-btn"
                                                    onclick="window.openCrmProformaLink({{ $opp->id }})"
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

        function openProformaLink(opportunityId) {
            const editModal = proformaEditModal();
            const editIframe = proformaEditIframe();
            const editTitle = proformaEditTitle();

            if (!editModal || !editIframe) {
                return;
            }

            closeProformaShow();
            editIframe.src = `${crmUrlBase}/opportunities/${opportunityId}/proforma/link`;
            if (editTitle) {
                editTitle.textContent = 'اتصال پیش‌فاکتور موجود';
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
        window.openCrmProformaLink = openProformaLink;

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
                if (!event.data.invoice?.id) {
                    if (typeof window.showErpToast === 'function') {
                        window.showErpToast(event.data.message || 'ذخیره فاکتور تایید نشد. دوباره تلاش کنید.', 'danger');
                    }
                    return;
                }

                closeProformaEdit();
                if (typeof window.showErpToast === 'function') {
                    window.showErpToast(event.data.message || 'فاکتور ذخیره شد.');
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

    <x-erp.ui.invoice-iframe-modals />
</x-erp.ui.list-page>
