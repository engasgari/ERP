<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">ویرایش پروژه</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl rounded-lg bg-white p-6 shadow">
            <div class="mb-6">
                <h2 class="text-lg font-black text-slate-800">{{ $project->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">مشخصات پروژه، کارفرما، بودجه و وضعیت تولید را از همین فرم ویرایش کنید.</p>
            </div>

            @include('projects._form')
        </div>
    </div>
</x-app-layout>
