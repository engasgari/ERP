<x-erp.ui.list-page

    title="وظایف CRM"

    description="پیگیری وظایف مرتبط با مشتریان و فرصت‌ها."

    route="crm.tasks.index"

>

    <div class="flex justify-end mb-2">

        <button type="button" wire:click="openCreate" class="erp-action-btn erp-action-edit">وظیفه جدید</button>

    </div>



    <x-slot name="filters">

        <x-erp.ui.filter-bar wire:submit.prevent>

            <div class="erp-filter-row">

                <label class="erp-filter-field md:col-span-2">جستجو

                    <input wire:model.live.debounce.400ms="search" placeholder="عنوان، توضیح">

                </label>

                <label class="erp-filter-field">وضعیت

                    <select wire:model.live="status">

                        <option value="">همه</option>

                        @foreach($statusOptions as $key => $label)

                            <option value="{{ $key }}">{{ $label }}</option>

                        @endforeach

                    </select>

                </label>

                <label class="erp-filter-field">اولویت

                    <select wire:model.live="priority">

                        <option value="">همه</option>

                        @foreach($priorityOptions as $key => $label)

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



    <x-crm.ui.responsive-list :has-items="$items->isNotEmpty()" empty-message="وظیفه‌ای یافت نشد.">
        <x-slot:cards>
            @foreach($items as $task)
                <x-crm.ui.list-card
                    wire:key="crm-task-card-{{ $task->id }}"
                    :title="$task->title"
                    :kicker="$task->party?->name ?? 'بدون مشتری'"
                    :badge-label="$task->status_label"
                    badge-tone="info"
                >
                    <x-crm.ui.list-field label="موعد">{{ $task->due_at ? formatJalaliDateTime($task->due_at) : '-' }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="اولویت">{{ $task->priority_label }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="مسئول">{{ $task->assignedUser?->name ?? '-' }}</x-crm.ui.list-field>
                    <x-slot:actions>
                        <x-erp.ui.row-actions>
                            <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openEdit({{ $task->id }})" />
                            @if($task->status !== 'completed')
                                <x-erp.ui.row-action icon="confirm" label="انجام شد" wire:click="complete({{ $task->id }})" tone="success" />
                            @endif
                        </x-erp.ui.row-actions>
                    </x-slot:actions>
                </x-crm.ui.list-card>
            @endforeach
        </x-slot:cards>
        <x-slot:table>
            <x-erp.ui.data-table
                :headers="['عنوان', 'مشتری', 'موعد', 'اولویت', 'وضعیت', 'مسئول', 'عملیات']"
                empty-message="وظیفه‌ای یافت نشد."
                :colspan="7"
            >
                @foreach($items as $task)
                    <tr wire:key="crm-task-{{ $task->id }}">
                        <td class="font-semibold">{{ $task->title }}</td>
                        <td>{{ $task->party?->name ?? '-' }}</td>
                        <td class="text-nowrap">{{ $task->due_at ? formatJalaliDateTime($task->due_at) : '-' }}</td>
                        <td>{{ $task->priority_label }}</td>
                        <td><x-erp.ui.status-badge :label="$task->status_label" tone="info" /></td>
                        <td>{{ $task->assignedUser?->name ?? '-' }}</td>
                        <td>
                            <x-erp.ui.row-actions>
                                <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openEdit({{ $task->id }})" />
                                @if($task->status !== 'completed')
                                    <x-erp.ui.row-action icon="confirm" label="انجام شد" wire:click="complete({{ $task->id }})" tone="success" />
                                @endif
                            </x-erp.ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </x-erp.ui.data-table>
        </x-slot:table>
    </x-crm.ui.responsive-list>



    <div>{{ $items->links() }}</div>



    @if($showTaskModal)

        <x-erp.ui.details-modal :title="$editingTaskId ? 'ویرایش وظیفه' : 'وظیفه جدید'">

            <x-slot name="close">

                <button type="button" class="erp-modal-close" wire:click="closeTaskModal">×</button>

            </x-slot>

            <x-slot name="footer">

                <button type="button" class="erp-action-btn" wire:click="closeTaskModal">انصراف</button>

                <button type="button" class="erp-action-btn erp-action-edit" wire:click="storeTask">{{ $editingTaskId ? 'به‌روزرسانی' : 'ذخیره' }}</button>

            </x-slot>

            <label class="erp-filter-field md:col-span-2 mb-3 block">مشتری (اختیاری)

                <select wire:model="form_party_id">

                    <option value="">—</option>

                    @foreach($customers as $customer)

                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->code }})</option>

                    @endforeach

                </select>

            </label>

            @include('livewire.crm.partials.task-form', ['priorityOptions' => $priorityOptions, 'statusOptions' => $statusOptions, 'users' => $users])

        </x-erp.ui.details-modal>

    @endif

</x-erp.ui.list-page>

