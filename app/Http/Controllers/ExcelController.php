<?php

namespace App\Http\Controllers;

//use App\Models\User;
use App\Exports\UsersExport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ExcelController extends Controller
{

    public function importView()
    {
        return view('excel.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt|max:10240'
        ]);

        try {
            $file = $request->file('file');
            $filePath = $file->getRealPath();

            $importedCount = 0;
            $rowCount = 0;

            if (($handle = fopen($filePath, 'r')) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $rowCount++;

                    // رد کردن هدر (ردیف اول)
                    if ($rowCount === 1) continue;

                    // ایجاد کاربر جدید اگر داده کافی وجود دارد
                    if (count($data) >= 2 && !empty($data[0]) && !empty($data[1])) {
                        User::create([
                            'name' => $data[0],
                            'email' => $data[1],
                            'password' => bcrypt($data[2] ?? 'password123'),
                        ]);
                        $importedCount++;
                    }
                }
                fclose($handle);
            }

            return back()->with('success', "✅ $importedCount کاربر با موفقیت وارد شدند.");

        } catch (\Exception $e) {
            Log::error('Import error: ' . $e->getMessage());
            return back()->with('error', '❌ خطا در پردازش فایل: ' . $e->getMessage());
        }
    }

    public function export()
    {
        try {
            $users = User::select('name', 'email', 'created_at')->get();

            $fileName = 'users_' . str_replace('/', '-', todayJalaliDate()) . '.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ];

            $callback = function() use ($users) {
                $file = fopen('php://output', 'w');

                // اضافه کردن BOM برای نمایش صحیح فارسی در Excel
                fwrite($file, "\xEF\xBB\xBF");

                // هدرهای فارسی
                fputcsv($file, ['نام', 'ایمیل', 'تاریخ ثبت نام']);

                // داده‌ها
                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->name,
                        $user->email,
                        verta($user->created_at)->format('Y/m/d H:i')
                    ]);
                }

                fclose($file);
            };

            return Response::stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());
            return back()->with('error', '❌ خطا در ایجاد فایل خروجی: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        try {
            $fileName = 'template_users.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ];

            $callback = function() {
                $file = fopen('php://output', 'w');

                // اضافه کردن BOM برای نمایش صحیح فارسی در Excel
                fwrite($file, "\xEF\xBB\xBF");

                // هدرهای فارسی
                fputcsv($file, ['نام', 'ایمیل', 'رمز عبور']);

                // داده‌های نمونه
                fputcsv($file, ['علی محمدی', 'ali@example.com', 'password123']);
                fputcsv($file, ['فاطمه احمدی', 'fatemeh@example.com', 'password123']);
                fputcsv($file, ['محمد رضایی', 'mohammad@example.com', 'password123']);

                fclose($file);
            };

            return Response::stream($callback, 200, $headers);

        } catch (\Exception $e) {
            return back()->with('error', '❌ خطا در ایجاد فایل نمونه: ' . $e->getMessage());
        }
    }
}
