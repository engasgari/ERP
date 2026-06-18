<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">عدم دسترسی</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-red-800">
                <h2 class="text-lg font-bold">شما به این قسمت دسترسی ندارید</h2>
                <p class="mt-2 text-sm text-red-700">
                    برای فعال شدن این بخش، مدیر سیستم باید دسترسی لازم را برای کاربر شما تنظیم کند.
                </p>
            </div>

            <div class="mt-4">
                <a href="{{ route('dashboard') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    بازگشت به داشبورد
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
