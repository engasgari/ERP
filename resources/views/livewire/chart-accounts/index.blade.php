<x-erp.ui.list-page
    title="سرفصل‌های مالی"
    description="نمایش درختواره‌ای کدینگ: گروه ← کل ← معین ← تفصیل."
    route="chart-accounts.index"
    :actions="[
        ['label' => 'سرفصل جدید', 'url' => route('chart-accounts.create'), 'class' => 'erp-action-edit'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="row g-2 g-md-3 align-items-end">
                <label class="erp-filter-field col-12 col-md-6 col-lg-4">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="کد، عنوان یا والد">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">سطح
                    <select wire:model.live="level">
                        <option value="">همه</option>
                        @foreach($levelLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">ماهیت
                    <select wire:model.live="nature">
                        <option value="">همه</option>
                        @foreach($natureLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">نوع
                    <select wire:model.live="is_system">
                        <option value="">همه</option>
                        <option value="1">سیستمی</option>
                        <option value="0">دستی</option>
                    </select>
                </label>
                <div class="col-12 col-lg-2 d-grid">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    @php
        $collectExpandable = function (array $nodes) use (&$collectExpandable): array {
            $ids = [];
            foreach ($nodes as $node) {
                if (($node['child_count'] ?? 0) > 0) {
                    $ids[] = (int) $node['id'];
                    $ids = array_merge($ids, $collectExpandable($node['children'] ?? []));
                }
            }

            return $ids;
        };
        $expandableIds = $collectExpandable($tree);
        $expandedMap = [];
        foreach ($expandedIds as $expandedId) {
            $expandedMap[(string) $expandedId] = true;
        }
    @endphp

    <div
        class="coa-tree-shell"
        wire:loading.class="is-loading"
        wire:key="coa-tree-{{ md5(json_encode([$search, $level, $nature, $is_system])) }}"
        x-data="{
            expanded: @js($expandedMap),
            expandableIds: @js($expandableIds),
            isOpen(id) { return !!this.expanded[String(id)]; },
            toggle(id) {
                const key = String(id);
                if (this.expanded[key]) {
                    delete this.expanded[key];
                } else {
                    this.expanded[key] = true;
                }
            },
            expandAll() {
                const next = {};
                this.expandableIds.forEach((id) => { next[String(id)] = true; });
                this.expanded = next;
            },
            collapseAll() {
                this.expanded = {};
            }
        }"
    >
        <div class="coa-toolbar">
            <div class="coa-toolbar__summary">
                <span class="coa-toolbar__count">{{ number_format($accountCount) }} سرفصل</span>
                <span class="coa-toolbar__hint">
                    @if($hasActiveFilters)
                        مسیر حساب‌های مطابق فیلتر باز شده است.
                    @else
                        روی <strong>+</strong> کنار هر گروه / کل / معین بزنید تا زیرمجموعه‌اش باز شود.
                    @endif
                </span>
            </div>
            <div class="coa-toolbar__actions">
                <button type="button" class="erp-action-btn" @click="expandAll()">باز کردن همه</button>
                <button type="button" class="erp-action-btn" @click="collapseAll()">بستن همه</button>
            </div>
        </div>

        <div class="coa-legend" aria-hidden="true">
            <span class="coa-legend__item is-group">گروه</span>
            <span class="coa-legend__item is-ledger">کل</span>
            <span class="coa-legend__item is-subsidiary">معین</span>
            <span class="coa-legend__item is-detail">تفصیل</span>
        </div>

        <div wire:loading.remove class="coa-tree">
            @forelse($tree as $node)
                @include('livewire.chart-accounts.partials.tree-node', ['node' => $node, 'depth' => 0])
            @empty
                <div class="coa-empty">سرفصلی یافت نشد.</div>
            @endforelse
        </div>

        <div wire:loading class="coa-loading">در حال بارگذاری درخت کدینگ...</div>
    </div>

    @if($editingId)
        <x-erp.ui.details-modal title="ویرایش سرفصل مالی">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeEditModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeEditModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveEdit" wire:loading.attr="disabled">ذخیره تغییرات</button>
            </x-slot>
            <div class="erp-filter-row">
                <label class="erp-filter-field">کد
                    <input wire:model="edit_code" dir="ltr">
                    @error('edit_code') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="erp-filter-field">عنوان
                    <input wire:model="edit_title">
                    @error('edit_title') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="erp-filter-field">سطح
                    <select wire:model="edit_level">
                        @foreach($levelLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('edit_level') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="erp-filter-field">ماهیت
                    <select wire:model="edit_nature">
                        @foreach($natureLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('edit_nature') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="erp-filter-field md:col-span-2">والد
                    <select wire:model="edit_parent_id">
                        <option value="">بدون والد</option>
                        @foreach($parentOptions as $parent)
                            <option value="{{ $parent->id }}">
                                {{ $parent->code }} - {{ $parent->title }}
                                ({{ $levelLabels[$parent->level] ?? $parent->level }})
                            </option>
                        @endforeach
                    </select>
                    @error('edit_parent_id') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>
        </x-erp.ui.details-modal>
    @endif
</x-erp.ui.list-page>

<style>
    .coa-tree-shell {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .coa-tree-shell.is-loading {
        opacity: 0.72;
    }

    .coa-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    .coa-toolbar__summary {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        min-width: 0;
    }

    .coa-toolbar__count {
        font-size: 0.92rem;
        font-weight: 700;
        color: #0f172a;
    }

    .coa-toolbar__hint {
        font-size: 0.78rem;
        color: #64748b;
    }

    .coa-toolbar__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .coa-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .coa-legend__item {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.28rem 0.7rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        border: 1px solid transparent;
    }

    .coa-legend__item.is-group,
    .coa-row.is-group .coa-level-badge {
        color: #1e3a8a;
        background: #dbeafe;
        border-color: #bfdbfe;
    }

    .coa-legend__item.is-ledger,
    .coa-row.is-ledger .coa-level-badge {
        color: #0f766e;
        background: #ccfbf1;
        border-color: #99f6e4;
    }

    .coa-legend__item.is-subsidiary,
    .coa-row.is-subsidiary .coa-level-badge {
        color: #9a3412;
        background: #ffedd5;
        border-color: #fed7aa;
    }

    .coa-legend__item.is-detail,
    .coa-row.is-detail .coa-level-badge {
        color: #475569;
        background: #f1f5f9;
        border-color: #e2e8f0;
    }

    .coa-tree {
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .coa-empty,
    .coa-loading {
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
        font-size: 0.92rem;
    }

    .coa-node + .coa-node > .coa-row {
        border-top: 1px solid #f1f5f9;
    }

    .coa-children {
        border-top: 1px solid #f1f5f9;
        background: linear-gradient(90deg, rgba(241, 245, 249, 0.55), transparent 12rem);
    }

    .coa-row {
        --coa-title-size: 0.82rem;
        --coa-code-size: 0.74rem;
        --coa-meta-size: 0.66rem;
        --coa-badge-size: 0.62rem;
        --coa-toggle-size: 1.35rem;
        --coa-row-pad-y: 0.42rem;
        --coa-row-gap: 0.4rem;

        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--coa-row-gap) 0.75rem;
        padding: var(--coa-row-pad-y) 0.75rem;
        padding-inline-start: calc(0.75rem + (var(--coa-depth, 0) * 1.15rem));
        transition: background-color 0.15s ease;
    }

    .coa-row:hover {
        background: #f8fafc;
    }

    /* گروه: بزرگ‌تر و پررنگ‌تر */
    .coa-row.is-group {
        --coa-title-size: 0.92rem;
        --coa-code-size: 0.8rem;
        --coa-meta-size: 0.7rem;
        --coa-badge-size: 0.66rem;
        --coa-toggle-size: 1.55rem;
        --coa-row-pad-y: 0.58rem;
        background: linear-gradient(90deg, rgba(219, 234, 254, 0.45), transparent 55%);
    }

    .coa-row.is-group:hover {
        background: linear-gradient(90deg, rgba(219, 234, 254, 0.7), #f8fafc 60%);
    }

    /* کل */
    .coa-row.is-ledger {
        --coa-title-size: 0.84rem;
        --coa-code-size: 0.76rem;
        --coa-meta-size: 0.66rem;
        --coa-badge-size: 0.62rem;
        --coa-toggle-size: 1.4rem;
        --coa-row-pad-y: 0.46rem;
        background: linear-gradient(90deg, rgba(204, 251, 241, 0.28), transparent 50%);
    }

    /* معین */
    .coa-row.is-subsidiary {
        --coa-title-size: 0.76rem;
        --coa-code-size: 0.7rem;
        --coa-meta-size: 0.62rem;
        --coa-badge-size: 0.58rem;
        --coa-toggle-size: 1.28rem;
        --coa-row-pad-y: 0.36rem;
        --coa-row-gap: 0.32rem;
    }

    /* تفصیل: ریزترین */
    .coa-row.is-detail {
        --coa-title-size: 0.7rem;
        --coa-code-size: 0.66rem;
        --coa-meta-size: 0.58rem;
        --coa-badge-size: 0.54rem;
        --coa-toggle-size: 1.15rem;
        --coa-row-pad-y: 0.28rem;
        --coa-row-gap: 0.28rem;
        color: #475569;
    }

    .coa-row__main,
    .coa-row__meta {
        display: flex;
        align-items: center;
        gap: var(--coa-row-gap);
        min-width: 0;
    }

    .coa-row__main {
        flex: 1 1 18rem;
    }

    .coa-row__meta {
        flex: 0 1 auto;
        justify-content: flex-end;
    }

    .coa-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--coa-toggle-size);
        height: var(--coa-toggle-size);
        flex: 0 0 var(--coa-toggle-size);
        border-radius: 0.45rem;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: calc(var(--coa-toggle-size) * 0.58);
        font-weight: 800;
        line-height: 1;
        cursor: pointer;
        box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04);
    }

    .coa-toggle:hover {
        border-color: #94a3b8;
        background: #f8fafc;
    }

    .coa-toggle--leaf {
        border-style: dashed;
        border-color: #e2e8f0;
        background: transparent;
        box-shadow: none;
        cursor: default;
    }

    .coa-level-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.6rem;
        padding: 0.1rem 0.38rem;
        border-radius: 999px;
        border: 1px solid transparent;
        font-size: var(--coa-badge-size);
        font-weight: 800;
        white-space: nowrap;
    }

    .coa-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: var(--coa-code-size);
        font-weight: 700;
        color: #334155;
        text-decoration: none;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .coa-row.is-detail .coa-code {
        color: #64748b;
        font-weight: 600;
    }

    .coa-code:hover,
    .coa-title:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }

    .coa-title-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.05rem;
        min-width: 0;
    }

    .coa-title {
        color: #0f172a;
        font-size: var(--coa-title-size);
        font-weight: 700;
        text-decoration: none;
        overflow-wrap: anywhere;
        line-height: 1.35;
    }

    .coa-row.is-group .coa-title {
        font-weight: 800;
    }

    .coa-row.is-ledger .coa-title {
        font-weight: 700;
    }

    .coa-row.is-subsidiary .coa-title {
        font-weight: 600;
        color: #1e293b;
    }

    .coa-row.is-detail .coa-title {
        font-weight: 500;
        color: #64748b;
    }

    .coa-submeta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.55rem;
    }

    .coa-child-count,
    .coa-doc-count,
    .coa-turnover {
        font-size: calc(var(--coa-meta-size) * 0.95);
        white-space: nowrap;
    }

    .coa-child-count {
        color: #94a3b8;
    }

    .coa-doc-count {
        font-weight: 700;
        padding: 0.05rem 0.4rem;
        border-radius: 999px;
        border: 1px solid transparent;
    }

    .coa-doc-count.has-turnover {
        color: #9a3412;
        background: #fff7ed;
        border-color: #fed7aa;
    }

    .coa-doc-count.no-turnover {
        color: #64748b;
        background: #f8fafc;
        border-color: #e2e8f0;
    }

    .coa-turnover {
        color: #64748b;
        font-weight: 600;
    }

    .coa-turnover.is-empty {
        color: #94a3b8;
        font-weight: 500;
    }

    .coa-nature,
    .coa-system {
        font-size: var(--coa-meta-size);
        font-weight: 600;
        white-space: nowrap;
    }

    .coa-nature {
        color: #64748b;
    }

    .coa-system {
        padding: 0.08rem 0.4rem;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
    }

    .coa-system.is-system {
        color: #1d4ed8;
        background: #eff6ff;
        border-color: #bfdbfe;
    }

    .coa-system.is-manual {
        color: #475569;
        background: #f8fafc;
    }

    .coa-row.is-detail .coa-nature,
    .coa-row.is-detail .coa-system,
    .coa-row.is-detail .coa-child-count {
        opacity: 0.85;
    }

    @media (max-width: 768px) {
        .coa-row {
            align-items: flex-start;
        }

        .coa-row__meta {
            width: 100%;
            justify-content: flex-start;
            padding-inline-start: 2.25rem;
        }
    }

    [x-cloak] {
        display: none !important;
    }
</style>
