@props(['selectedCount' => 0, 'actions' => []])
@if($selectedCount > 0)
    <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="font-bold text-slate-700">{{ number_format($selectedCount) }} مورد انتخاب شده است.</div>
        <x-erp.ui.action-menu :actions="$actions" />
    </div>
@endif
