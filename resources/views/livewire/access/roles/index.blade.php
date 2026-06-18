<div class="py-4 py-md-5">
    <div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
            <h2 class="text-xl font-bold mb-0">نقش‌ها</h2>
            <div class="d-grid d-sm-flex gap-2">
                <a href="{{ route('access.users.index') }}" class="erp-action-btn erp-action-detail text-center">کاربران</a>
                <a href="{{ route('access.roles.create') }}" class="erp-action-btn erp-action-edit text-center">نقش جدید</a>
            </div>
        </div>

        @include('livewire.partials.flash')

        <form wire:submit.prevent class="erp-ui-filter-bar">
            <div class="row g-2 g-md-3 align-items-end">
                <label class="erp-filter-field col-12 col-lg-9">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="عنوان یا کلید نقش">
                </label>
                <div class="col-12 col-lg-3 d-grid">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </form>

        <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>

        <div class="table-responsive overflow-x-auto">
            <table class="erp-ui-data-table w-full">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>کلید</th>
                        <th>توضیح</th>
                        <th>کاربران</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($roles as $role)
                    <tr wire:key="role-{{ $role->id }}">
                        <td>
                            @if($role->is_system)
                                {{ $role->title }}
                            @else
                                <input class="form-control form-control-sm" value="{{ $role->title }}" wire:change="updateField({{ $role->id }}, 'title', $event.target.value)">
                            @endif
                        </td>
                        <td dir="ltr">{{ $role->name }}</td>
                        <td>
                            @if($role->is_system)
                                {{ $role->description ?: '-' }}
                            @else
                                <input class="form-control form-control-sm" value="{{ $role->description }}" wire:change="updateField({{ $role->id }}, 'description', $event.target.value)" placeholder="توضیح">
                            @endif
                        </td>
                        <td>{{ number_format($role->users_count) }}</td>
                        <td>
                            <div class="d-grid d-sm-flex gap-2">
                                <a href="{{ route('access.roles.edit', $role) }}" class="erp-action-btn erp-action-edit text-center">ویرایش کامل</a>
                                @unless($role->is_system)
                                    <button type="button" wire:click="delete({{ $role->id }})" wire:confirm="نقش حذف شود؟" wire:loading.attr="disabled" wire:target="delete({{ $role->id }})" class="erp-action-btn erp-action-delete">حذف</button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-slate-500 py-6">نقشی یافت نشد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $roles->links() }}</div>
    </div>
</div>
