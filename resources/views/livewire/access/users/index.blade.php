<div class="py-4 py-md-5">
    <div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
            <h2 class="text-xl font-bold mb-0">کاربران و دسترسی‌ها</h2>
            <div class="d-grid d-sm-flex gap-2">
                <a href="{{ route('access.roles.index') }}" class="erp-action-btn erp-action-detail text-center">نقش‌ها</a>
                <a href="{{ route('access.users.create') }}" class="erp-action-btn erp-action-edit text-center">کاربر جدید</a>
            </div>
        </div>

        @include('livewire.partials.flash')

        <form wire:submit.prevent class="erp-ui-filter-bar">
            <div class="row g-2 g-md-3 align-items-end">
                <label class="erp-filter-field col-12 col-lg-9">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="نام، ایمیل یا نقش">
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
                        <th>نام</th>
                        <th>ایمیل</th>
                        <th>نقش‌ها</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td>
                            <input class="form-control form-control-sm" value="{{ $user->name }}" wire:change="updateField({{ $user->id }}, 'name', $event.target.value)">
                        </td>
                        <td>
                            <input type="email" dir="ltr" class="form-control form-control-sm" value="{{ $user->email }}" wire:change="updateField({{ $user->id }}, 'email', $event.target.value)">
                        </td>
                        <td>{{ $user->roles->pluck('title')->join('، ') ?: '-' }}</td>
                        <td>
                            <div class="d-grid d-sm-flex gap-2">
                                <a href="{{ route('access.users.edit', $user) }}" class="erp-action-btn erp-action-edit text-center">ویرایش کامل</a>
                                <a href="{{ route('access.users.employee-access', $user) }}" class="erp-action-btn erp-action-detail text-center">دسترسی کارمندها</a>
                                @if($user->id !== auth()->id())
                                    <button type="button" wire:click="delete({{ $user->id }})" wire:confirm="کاربر حذف شود؟" wire:loading.attr="disabled" wire:target="delete({{ $user->id }})" class="erp-action-btn erp-action-delete">حذف</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-slate-500 py-6">کاربری یافت نشد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $users->links() }}</div>
    </div>
</div>
