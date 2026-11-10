@php
    $hasChildren = ($node['child_count'] ?? 0) > 0;
    $nodeId = (int) $node['id'];
    $depth = (int) ($depth ?? 0);
    $levelClass = match ($node['level'] ?? '') {
        'group' => 'is-group',
        'ledger' => 'is-ledger',
        'subsidiary' => 'is-subsidiary',
        'detail' => 'is-detail',
        default => '',
    };
@endphp

<div class="coa-node" wire:key="coa-node-{{ $nodeId }}">
    <div class="coa-row {{ $levelClass }}" style="--coa-depth: {{ $depth }}">
        <div class="coa-row__main">
            @if($hasChildren)
                <button
                    type="button"
                    class="coa-toggle"
                    @click="toggle({{ $nodeId }})"
                    :aria-expanded="isOpen({{ $nodeId }})"
                    :title="isOpen({{ $nodeId }}) ? 'بستن زیرمجموعه‌ها' : 'باز کردن زیرمجموعه‌ها'"
                >
                    <span class="coa-toggle__icon" x-text="isOpen({{ $nodeId }}) ? '−' : '+'"></span>
                </button>
            @else
                <span class="coa-toggle coa-toggle--leaf" aria-hidden="true"></span>
            @endif

            <span class="coa-level-badge" title="سطح حساب">{{ $node['level_label'] }}</span>

            <a
                href="{{ route('financial-reports.account-statement', $nodeId) }}"
                class="coa-code"
                title="گردش حساب"
            >{{ $node['code'] }}</a>

            <div class="coa-title-wrap">
                <a
                    href="{{ route('financial-reports.account-statement', $nodeId) }}"
                    class="coa-title"
                >{{ $node['title'] }}</a>
                <div class="coa-submeta">
                    @if($hasChildren)
                        <span class="coa-child-count">{{ $node['child_count'] }} زیرمجموعه</span>
                    @endif
                    <span
                        class="coa-doc-count {{ ($node['has_turnover'] ?? false) ? 'has-turnover' : 'no-turnover' }}"
                        title="{{ $hasChildren ? 'جمع اسناد زیرمجموعه‌ها (به‌همراه خود سرفصل)' : 'تعداد اسناد این سرفصل' }}"
                    >
                        {{ number_format((int) ($node['document_count'] ?? 0)) }} سند
                    </span>
                    @if($node['has_turnover'] ?? false)
                        <span class="coa-turnover" title="جمع بدهکار و بستانکار">
                            گردش {{ formatMoney((float) ($node['turnover'] ?? 0)) }}
                        </span>
                    @else
                        <span class="coa-turnover is-empty">بدون گردش</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="coa-row__meta">
            <span class="coa-nature">{{ $node['nature_label'] }}</span>
            <span class="coa-system {{ ($node['is_active'] ?? true) ? 'is-manual' : 'is-system' }}" title="وضعیت حساب">
                {{ ($node['is_active'] ?? true) ? 'فعال' : 'غیرفعال' }}
            </span>
            <span class="coa-system {{ $node['is_system'] ? 'is-system' : 'is-manual' }}">
                {{ $node['is_system'] ? 'سیستمی' : 'دستی' }}
            </span>
            <div class="coa-actions" onclick="event.stopPropagation()">
                @php
                    $actions = [
                        ['type' => 'button', 'label' => 'ویرایش', 'class' => 'erp-action-edit', 'attrs' => ['wire:click' => 'edit(' . $nodeId . ')']],
                    ];
                    if ($node['can_delete'] ?? false) {
                        $confirm = ($node['child_count'] ?? 0) > 0
                            ? 'این سرفصل و همه زیرمجموعه‌های بدون گردش حذف می‌شوند. ادامه می‌دهید؟'
                            : 'آیا از حذف این سرفصل مطمئن هستید؟';
                        $actions[] = [
                            'type' => 'button',
                            'label' => 'حذف',
                            'class' => 'erp-action-delete',
                            'attrs' => [
                                'wire:click' => 'delete(' . $nodeId . ')',
                                'wire:confirm' => $confirm,
                                'wire:loading.attr' => 'disabled',
                                'wire:target' => 'delete(' . $nodeId . ')',
                            ],
                        ];
                    }
                @endphp
                <x-erp.ui.action-menu :actions="$actions" />
            </div>
        </div>
    </div>

    @if($hasChildren)
        <div
            class="coa-children"
            x-show="isOpen({{ $nodeId }})"
            x-cloak
        >
            @foreach($node['children'] as $child)
                @include('livewire.chart-accounts.partials.tree-node', [
                    'node' => $child,
                    'depth' => $depth + 1,
                ])
            @endforeach
        </div>
    @endif
</div>
